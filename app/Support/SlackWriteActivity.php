<?php

namespace App\Support;

use App\Jobs\PostSlackWriteActivity;
use App\Models\ActivityLogEntry;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Models\LeadStatus;
use App\Models\LeadStatusHistory;
use App\Models\RawDataAssignmentLog;
use App\Models\RawDataImportRejection;
use App\Models\Setting;
use App\Models\SupportTicketAssignmentLog;
use App\Models\SupportTicketStatusLog;
use App\Models\User;
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

    /** Columns holding a user id — shown as the person's name ("assigned_by: Asha Rai → Bikash KC"). */
    private const USER_COLUMNS = [
        'created_by', 'user_id', 'author_id', 'assigned_to', 'assigned_by', 'assigned_user_id', 'performed_by',
        'changed_by', 'uploaded_by', 'updated_by', 'last_updated_by', 'support_pin_generated_by', 'sent_by',
        'reporting_manager_id', 'raised_by', 'finalized_by', 'conducted_by', 'completed_by', 'closed_by',
    ];

    /** Other id columns worth naming: column => [model, attribute to show]. */
    private const NAMED_COLUMNS = [
        'lead_status_id' => [LeadStatus::class, 'name'],
    ];

    private const MAX_LINES = 25;

    private const MAX_FIELDS = 8;

    /** @var list<string> */
    private array $lines = [];

    private int $overflow = 0;

    private ?string $actor = null;

    private ?string $context = null;

    /** @var array<string, string> "Model:id" => display name, per message */
    private array $names = [];

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
        $this->names = [];
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

        // Clickable: the record's page (or, for a deleted record / one
        // without a page, the record it belongs to). The page still asks
        // for login and checks permissions.
        if ($url = RecordUrl::forModel($model, exists: $action !== 'deleted')) {
            $ref = $this->link($url, $ref);
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
                : "{$key}: ".$this->display($key, $model->getRawOriginal($key)).' → '.$this->display($key, $new);
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

    /**
     * A changed value as people read it: user and status ids become names
     * (a deleted user still shows their name; an unknown id stays "#11").
     */
    private function display(string $key, mixed $value): string
    {
        $lookup = in_array($key, self::USER_COLUMNS, true) ? [User::class, 'name'] : (self::NAMED_COLUMNS[$key] ?? null);

        if (! $lookup || ! is_numeric($value)) {
            return $this->value($value);
        }

        [$modelClass, $attribute] = $lookup;

        return $this->names["{$modelClass}:{$value}"] ??= $this->clip(
            (string) ($modelClass::query()->withoutGlobalScopes()->whereKey($value)->value($attribute) ?? "#{$value}"),
            60,
        );
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

    /** Slack link: <url|text>. The text is already escaped; "|" would end it early. */
    private function link(string $url, string $text): string
    {
        return '<'.$url.'|'.str_replace('|', '/', $text).'>';
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
            $label = $request->method().' /'.ltrim($request->path(), '/');

            // Links to the nearest page for that path (e.g. PUT /leads/12 →
            // the lead), which asks for login and checks permissions.
            $url = RecordUrl::pageForPath($request->path());

            return $url ? $this->link($url, $this->escape($label)) : '`'.$label.'`';
        }

        $command = implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 2));

        return 'artisan `'.($command ?: 'console').'`';
    }
}
