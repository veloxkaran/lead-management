<?php

namespace App\Support;

use App\Jobs\PostSlackWriteActivity;
use App\Models\ActivityLogEntry;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Models\LeadStatusHistory;
use App\Models\RawDataAssignmentLog;
use App\Models\RawDataImportRejection;
use App\Models\Setting;
use App\Models\SupportTicketAssignmentLog;
use App\Models\SupportTicketStatusLog;
use App\Models\WhatsappMessage;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Reports every create/update/delete on an App\Models model to the Slack
 * webhook in SLACK_ACTIVITY_WEBHOOK_URL (blank = off).
 *
 * Writes are buffered and posted as ONE message per web request, artisan
 * command or queue job (flush points live in SlackWriteActivityServiceProvider):
 * a 500-row import is one post, not 500, which keeps the channel readable
 * and stays under Slack's ~1 message/second webhook limit.
 *
 * Not seen: query-builder mass updates/deletes (Model::query()->update()),
 * ::insert(), and saveQuietly() — Eloquent fires no model events for those.
 */
class SlackWriteActivity
{
    /** High-volume or derived tables that would drown the channel. */
    public const EXCLUDED_MODELS = [
        ActivityLogEntry::class,           // mirrors writes already reported
        EmailLog::class,                   // per-email delivery status churn
        CampaignRecipient::class,          // one row per campaign recipient
        WhatsappMessage::class,
        LeadStatusHistory::class,          // side effect of the Lead update itself
        SupportTicketStatusLog::class,     // side effect of the ticket update
        SupportTicketAssignmentLog::class,
        RawDataAssignmentLog::class,
        RawDataImportRejection::class,     // one row per rejected import row
    ];

    /** Settings rows rewritten by background sync, not by people. */
    private const EXCLUDED_SETTING_PREFIXES = ['mailbox_sync.'];

    /** Bookkeeping columns: an update touching only these isn't reported. */
    private const IGNORED_ATTRIBUTES = ['created_at', 'updated_at', 'remember_token'];

    private const SENSITIVE = '/password|secret|token|api_?key|otp/i';

    private const LABEL_ATTRIBUTES = ['name', 'title', 'company_name', 'subject', 'key', 'email'];

    private const MAX_LINES = 25;

    private const MAX_FIELDS = 8;

    /** @var list<string> */
    private array $lines = [];

    private int $overflow = 0;

    private ?string $actor = null;

    private ?string $context = null;

    public function enabled(): bool
    {
        return filled(config('services.slack.activity_webhook_url'));
    }

    public function record(string $action, Model $model): void
    {
        if (! $this->enabled() || ! $this->shouldReport($model)) {
            return;
        }

        // Built now: on "updated" the old values are only readable during the event.
        $line = $this->describe($action, $model);

        if ($line === null) {
            return;
        }

        $actor = auth()->user()?->name;
        $context = $this->context ?? $this->currentContext();

        // A rolled-back write never happened. Runs immediately when no
        // transaction is open, otherwise only once it commits.
        $model->getConnection()->afterCommit(function () use ($line, $actor, $context) {
            $this->actor ??= $actor;
            $this->context ??= $context;

            if (count($this->lines) < self::MAX_LINES) {
                $this->lines[] = $line;
            } else {
                $this->overflow++;
            }
        });
    }

    /** Starts a fresh message for a queue job, sending anything still pending first. */
    public function startJob(string $jobName): void
    {
        $this->flush();
        $this->context = 'queue `'.class_basename($jobName).'`';
    }

    public function flush(): void
    {
        if ($this->lines === []) {
            $this->reset();

            return;
        }

        $text = '*'.$this->escape($this->actor ?? 'System').'* · '.($this->context ?? 'app')."\n"
            .implode("\n", $this->lines)
            .($this->overflow ? "\n…and {$this->overflow} more write(s)" : '');

        $this->reset();

        // Posted directly — flush runs after the response is sent / after a
        // job finishes — so it works without a queue worker. Only a failed
        // post (Slack down, rate limited) goes to the queue to be retried.
        if (! PostSlackWriteActivity::deliver($text)) {
            PostSlackWriteActivity::dispatch($text);
        }
    }

    private function reset(): void
    {
        $this->lines = [];
        $this->overflow = 0;
        $this->actor = null;
        $this->context = null;
    }

    private function shouldReport(Model $model): bool
    {
        if (in_array($model::class, self::EXCLUDED_MODELS, true)) {
            return false;
        }

        if ($model instanceof Setting) {
            return ! Str::startsWith((string) $model->key, self::EXCLUDED_SETTING_PREFIXES);
        }

        return true;
    }

    private function describe(string $action, Model $model): ?string
    {
        $ref = class_basename($model).' #'.$model->getKey();

        if ($label = $this->label($model)) {
            $ref .= ' "'.$label.'"';
        }

        return match ($action) {
            'created' => "• :heavy_plus_sign: created {$ref}",
            'deleted' => "• :wastebasket: deleted {$ref}",
            'updated' => $this->describeUpdate($ref, $model),
            default => null,
        };
    }

    private function describeUpdate(string $ref, Model $model): ?string
    {
        $changes = array_diff_key($model->getChanges(), array_flip(self::IGNORED_ATTRIBUTES));

        if ($changes === []) {
            return null;
        }

        $fields = [];

        foreach (array_slice($changes, 0, self::MAX_FIELDS, true) as $key => $new) {
            $fields[] = $this->isSensitive($model, $key)
                ? "{$key}: [redacted]"
                : "{$key}: ".$this->value($model->getRawOriginal($key)).' → '.$this->value($new);
        }

        if (($more = count($changes) - self::MAX_FIELDS) > 0) {
            $fields[] = "+{$more} more";
        }

        return "• :pencil2: updated {$ref} — ".implode('; ', $fields);
    }

    private function label(Model $model): ?string
    {
        $attributes = $model->getAttributes();

        foreach (self::LABEL_ATTRIBUTES as $attribute) {
            $value = $attributes[$attribute] ?? null;

            if (is_string($value) && trim($value) !== '' && ! $this->isSensitive($model, $attribute)) {
                return $this->clip($value, 50);
            }
        }

        return null;
    }

    private function isSensitive(Model $model, string $attribute): bool
    {
        if (preg_match(self::SENSITIVE, $attribute) || in_array($attribute, $model->getHidden(), true)) {
            return true;
        }

        // A Setting's secret lives in "value"; its name is in "key".
        return $model instanceof Setting && $attribute === 'value' && preg_match(self::SENSITIVE, (string) $model->key);
    }

    private function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof BackedEnum => $this->clip((string) $value->value, 60),
            is_scalar($value), $value instanceof \Stringable => $this->clip((string) $value, 60),
            default => $this->clip((string) json_encode($value), 60),
        };
    }

    private function clip(string $value, int $limit): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($value)));

        return $this->escape(Str::limit($plain, $limit));
    }

    /** Slack mrkdwn treats &, < and > as control characters. */
    private function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    private function currentContext(): string
    {
        if (! app()->runningInConsole()) {
            $request = request();

            return '`'.$request->method().' /'.ltrim($request->path(), '/').'`';
        }

        $command = implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 2));

        return 'artisan `'.($command ?: 'console').'`';
    }
}
