<?php

namespace App\Services;

use App\Jobs\SendClientNotificationEmail;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry point for the client-facing notification email on a
 * Requirement/SupportTicket lifecycle event (created / status changed).
 * The actual rendering/sending/logging happens in the queued job — this
 * class only decides whether it's worth dispatching one at all.
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

        if (! $this->templates->findByKey($templateKey)) {
            return;
        }

        $variables['app_name'] ??= config('app.name');

        SendClientNotificationEmail::dispatch($templateKey, $toEmail, $variables, $related);
    }
}
