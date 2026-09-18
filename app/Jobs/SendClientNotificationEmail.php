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
 * or support-ticket request that triggered it. Rendering, sending, and the
 * email_logs row all happen here (on the worker), not in ClientNotifier —
 * a job failure must still leave a "failed" row instead of losing the
 * attempt silently.
 */
class SendClientNotificationEmail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|null>  $variables
     */
    public function __construct(
        public string $templateKey,
        public string $toEmail,
        public array $variables,
        public ?Model $related = null,
    ) {
        // Its own queue, not "default" — keeps it processable independently
        // of whatever else (e.g. Slack notification listeners) shares the
        // default queue on this connection.
        $this->onQueue('emails');
    }

    public function handle(EmailTemplateService $templates): void
    {
        $template = $templates->findByKey($this->templateKey);

        if (! $template) {
            return;
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

        try {
            Mail::to($this->toEmail)->send(new ClientNotificationMail($rendered['subject'], $rendered['body']));

            $log->status = EmailLogStatus::Sent;
            $log->sent_at = now();
        } catch (Throwable $e) {
            $log->status = EmailLogStatus::Failed;
            $log->error = $e->getMessage();
        }

        $log->save();
    }
}
