<?php

namespace App\Jobs;

use App\Enums\CampaignChannel;
use App\Enums\EmailLogStatus;
use App\Mail\ClientNotificationMail;
use App\Models\Announcement;
use App\Models\CampaignUnsubscribe;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Services\EmailTemplateService;
use App\Support\MailRetry;
use App\Support\PublicUrl;
use App\Support\RecipientEmailCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Queued so a slow/unavailable mail server can't hold up the requirement
 * or support-ticket request that triggered it. ClientNotifier has already
 * rendered the email into a "pending" email_logs row; this job only sends
 * it and records the outcome — a failure still leaves a "failed" row
 * instead of losing the attempt silently.
 *
 * Before sending: the address must be able to receive mail
 * (RecipientEmailCheck), and announcement recipients who unsubscribed in
 * the meantime are skipped. Failures where the server certainly didn't
 * take the message are retried after 1, 5 and 15 minutes (MailRetry).
 * Announcements carry an unsubscribe link + one-click List-Unsubscribe
 * headers, which Gmail/Yahoo require of bulk mail.
 */
class SendClientNotificationEmail implements ShouldQueue
{
    use Queueable;

    /** 1 send + MailRetry::DELAYS retries; overrides the worker's --tries. */
    public int $tries = 4;

    /** Log row was deleted before the worker got to it — nothing to send. */
    public bool $deleteWhenMissingModels = true;

    public ?EmailLog $log = null;

    // Legacy payload — jobs queued before the log row was created at
    // dispatch time carry these instead of $log. Kept so any still sitting
    // in the jobs table are sent rather than failing after a deploy.
    public ?string $templateKey = null;

    public ?string $toEmail = null;

    /** @var array<string, string|null> */
    public array $variables = [];

    public ?Model $related = null;

    public function __construct(EmailLog $log)
    {
        $this->log = $log;

        // Its own queue, not "default" — keeps it processable independently
        // of whatever else (e.g. Slack notification listeners) shares the
        // default queue on this connection.
        $this->onQueue('emails');
    }

    public function handle(EmailTemplateService $templates, RecipientEmailCheck $addressCheck): void
    {
        $log = $this->log ?? $this->legacyLog($templates);

        if (! $log) {
            return;
        }

        $isAnnouncement = $log->related instanceof Announcement;

        if ($isAnnouncement && CampaignUnsubscribe::isUnsubscribed(CampaignChannel::Email, mb_strtolower(trim($log->to_email)))) {
            $this->notSent($log, 'this address unsubscribed from these emails.');

            return;
        }

        $check = $addressCheck->check($log->to_email);

        if ($check['status'] === RecipientEmailCheck::INVALID) {
            $this->notSent($log, $check['reason']);

            return;
        }

        try {
            $images = $log->related instanceof Announcement
                ? $log->related->images->map->absolutePath()->filter(fn (string $path) => is_file($path))->values()->all()
                : [];

            $documents = $log->related instanceof Announcement
                ? $log->related->documents
                    ->filter(fn ($document) => is_file($document->absolutePath()))
                    ->map(fn ($document) => ['path' => $document->absolutePath(), 'name' => $document->original_name, 'mime' => $document->mime_type])
                    ->values()->all()
                : [];

            // Older rows (and legacy jobs) have no token yet — give them one so opens are tracked too.
            $log->tracking_token ??= Str::random(40);

            Mail::to($log->to_email)->send(new ClientNotificationMail(
                $log->subject, $log->body, $images, $documents,
                // A tracking image pointing at localhost would be broken
                // for every recipient and look like spam — leave it out.
                trackingUrl: PublicUrl::isPublic() ? route('email-logs.track-open', $log->tracking_token) : null,
                unsubscribeUrl: $isAnnouncement ? route('campaigns.unsubscribe', $log->tracking_token) : null,
                footer: $isAnnouncement ? Setting::get('campaign_footer') : null,
            ));

            $log->status = EmailLogStatus::Sent;
            $log->sent_at = now();
            $log->error = null;
        } catch (Throwable $e) {
            // Nothing was handed over — safe to try again shortly.
            if (($delay = MailRetry::delay($this->job, $e)) !== null) {
                $log->error = $e->getMessage();
                $log->save();
                $this->release($delay);

                return;
            }

            $log->status = EmailLogStatus::Failed;
            $log->error = $e->getMessage();
        }

        $log->save();
    }

    private function notSent(EmailLog $log, string $reason): void
    {
        $log->status = EmailLogStatus::Failed;
        $log->error = 'Not sent — '.$reason;
        $log->save();
    }

    /**
     * Called once the job has run out of attempts (e.g. worker killed or
     * timed out mid-send) — don't leave the row stuck on "pending".
     */
    public function failed(?Throwable $e): void
    {
        if ($this->log?->status === EmailLogStatus::Pending) {
            $this->log->update([
                'status' => EmailLogStatus::Failed,
                'error' => $e?->getMessage() ?? 'Job failed.',
            ]);
        }
    }

    private function legacyLog(EmailTemplateService $templates): ?EmailLog
    {
        $template = $this->templateKey ? $templates->findByKey($this->templateKey) : null;

        if (! $template || ! $this->toEmail) {
            return null;
        }

        $rendered = $templates->render($template, $this->variables);

        $log = new EmailLog([
            'to_email' => $this->toEmail,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'template_key' => $this->templateKey,
        ]);

        if ($this->related) {
            $log->related()->associate($this->related);
        }

        return $log;
    }
}
