<?php

namespace App\Jobs;

use App\Enums\EmailLogStatus;
use App\Mail\ClientNotificationMail;
use App\Models\EmailLog;
use App\Services\EmailTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Queued so a slow/unavailable mail server can't hold up the requirement
 * or support-ticket request that triggered it. ClientNotifier has already
 * rendered the email into a "pending" email_logs row; this job only sends
 * it and records the outcome — a failure still leaves a "failed" row
 * instead of losing the attempt silently.
 */
class SendClientNotificationEmail implements ShouldQueue
{
    use Queueable;

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

    public function handle(EmailTemplateService $templates): void
    {
        $log = $this->log ?? $this->legacyLog($templates);

        if (! $log) {
            return;
        }

        try {
            Mail::to($log->to_email)->send(new ClientNotificationMail($log->subject, $log->body));

            $log->status = EmailLogStatus::Sent;
            $log->sent_at = now();
            $log->error = null;
        } catch (Throwable $e) {
            $log->status = EmailLogStatus::Failed;
            $log->error = $e->getMessage();
        }

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
