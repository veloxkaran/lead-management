<?php

namespace App\Http\Controllers;

use App\Enums\CampaignChannel;
use App\Enums\CampaignRecipientStatus;
use App\Enums\EmailLogStatus;
use App\Models\CampaignRecipient;
use App\Models\CampaignUnsubscribe;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Support\CampaignSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public (no login) endpoints that confirm delivery, and the unsubscribe
 * page. Tracking answers the same way whether or not the token/message
 * matches, so it can't be used to probe which recipients exist.
 */
class CampaignTrackingController extends Controller
{
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * The 1×1 image in a campaign email — loading it means the email reached
     * the inbox and was opened.
     */
    public function open(string $token): Response
    {
        CampaignRecipient::where('tracking_token', $token)
            ->where('status', CampaignRecipientStatus::Sent)
            ->update(['status' => CampaignRecipientStatus::Delivered->value, 'delivered_at' => now()]);

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * The 1×1 image in client notification emails (Email Log) — same idea
     * as open(): loading it means the email arrived and was opened.
     */
    public function notificationOpen(string $token): Response
    {
        EmailLog::where('tracking_token', $token)
            ->where('status', EmailLogStatus::Sent)
            ->update(['status' => EmailLogStatus::Delivered->value, 'delivered_at' => now()]);

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Delivery report callback from the SMS gateway (GET or POST). The
     * parameter names and status values are set in Campaign Setup.
     */
    public function smsDelivery(Request $request, CampaignSettings $settings, string $secret): Response
    {
        abort_unless(hash_equals($settings->dlrSecret(), $secret), 404);

        $messageId = (string) $request->input($settings->get('sms_dlr_id_param', 'message_id'), '');
        $status = mb_strtolower(trim((string) $request->input($settings->get('sms_dlr_status_param', 'status'), '')));

        if ($messageId !== '' && $status !== '') {
            $delivered = in_array($status, $settings->list('sms_dlr_delivered_values') ?: ['delivered', 'delivrd', 'success'], true);
            $failed = in_array($status, $settings->list('sms_dlr_failed_values') ?: ['failed', 'undeliv', 'undelivered', 'rejected', 'expired'], true);

            if ($delivered || $failed) {
                CampaignRecipient::where('provider_message_id', $messageId)
                    ->whereIn('status', [CampaignRecipientStatus::Sent, CampaignRecipientStatus::Pending])
                    ->update($delivered
                        ? ['status' => CampaignRecipientStatus::Delivered->value, 'delivered_at' => now()]
                        : ['status' => CampaignRecipientStatus::Failed->value, 'error' => "Gateway delivery report: {$status}"]);
            }
        }

        return response('OK', 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * The unsubscribe link in a campaign email. Only asks — unsubscribing
     * on GET would let link scanners and mail-app prefetching unsubscribe
     * people who never clicked.
     */
    public function unsubscribeForm(string $token): Response
    {
        $recipient = $this->emailRecipient($token);

        return response()->view('campaigns.unsubscribe', [
            'recipient' => $recipient,
            'done' => $recipient && CampaignUnsubscribe::isUnsubscribed(CampaignChannel::Email, $recipient->address),
            'companyName' => Setting::get('company_name') ?: config('app.name'),
            'token' => $token,
        ]);
    }

    /**
     * Confirms the unsubscribe — the page's button, or a mail app's
     * one-click unsubscribe (RFC 8058: a POST with
     * "List-Unsubscribe=One-Click", no session or CSRF token).
     */
    public function unsubscribe(Request $request, string $token): Response
    {
        $recipient = $this->emailRecipient($token);

        if ($recipient) {
            CampaignUnsubscribe::firstOrCreate(
                ['channel' => CampaignChannel::Email, 'address' => $recipient->address],
                [
                    'campaign_id' => $recipient->campaign_id,
                    'reason' => $request->input('List-Unsubscribe') === 'One-Click' ? 'One-click unsubscribe (mail app)' : 'Unsubscribe link',
                ],
            );

            if (! $recipient->unsubscribed_at) {
                $recipient->update(['unsubscribed_at' => now()]);
            }
        }

        return response()->view('campaigns.unsubscribe', [
            'recipient' => $recipient,
            'done' => $recipient !== null,
            'companyName' => Setting::get('company_name') ?: config('app.name'),
            'token' => $token,
        ]);
    }

    private function emailRecipient(string $token): ?CampaignRecipient
    {
        return CampaignRecipient::where('tracking_token', $token)
            ->whereHas('campaign', fn ($q) => $q->withoutGlobalScopes()->where('channel', CampaignChannel::Email))
            ->first();
    }
}
