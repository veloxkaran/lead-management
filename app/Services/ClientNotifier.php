<?php

namespace App\Services;

use App\Enums\EmailLogStatus;
use App\Jobs\SendClientNotificationEmail;
use App\Models\EmailLog;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry point for the client-facing notification email on a
 * Requirement/SupportTicket lifecycle event (created / status changed).
 * Renders the email and records it as a "pending" email_logs row up front,
 * so it's visible in the Email Log as soon as it's queued — the queued job
 * then only sends it and flips the row to sent/failed.
 */
class ClientNotifier
{
    public function __construct(protected EmailTemplateService $templates)
    {
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    public function notify(string $templateKey, ?string $toEmail, array $variables, ?Model $related = null): void
    {
        if (blank($toEmail)) {
            return;
        }

        if (Setting::get('notifications_email_enabled', '1') === '0') {
            return;
        }

        $template = $this->templates->findByKey($templateKey);

        if (! $template) {
            return;
        }

        $variables['app_name'] ??= config('app.name');

        $rendered = $this->templates->render($template, $variables);

        $log = new EmailLog([
            'to_email' => $toEmail,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'template_key' => $templateKey,
            'status' => EmailLogStatus::Pending,
        ]);

        if ($related) {
            $log->related()->associate($related);
        }

        $log->save();

        SendClientNotificationEmail::dispatch($log);
    }
}
