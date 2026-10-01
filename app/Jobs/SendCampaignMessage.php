<?php

namespace App\Jobs;

use App\Enums\CampaignChannel;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\CampaignRecipient;
use App\Models\CampaignUnsubscribe;
use App\Services\CampaignMailer;
use App\Services\CampaignService;
use App\Services\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one campaign message and records the outcome on its recipient row.
 * Carries only the id (not the model) so a recipient cancelled while the
 * job waited is re-read and skipped. If the campaign was paused meanwhile,
 * the recipient goes back in line (queued_at cleared) to be queued again
 * on resume. Every failure is caught and recorded
 * as "failed" with the reason, rather than retried — a retry could send a
 * second copy if the first actually went out before the error.
 */
class SendCampaignMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $recipientId)
    {
        $this->onQueue('campaigns');
    }

    public function handle(CampaignMailer $mailer, SmsGateway $sms): void
    {
        $recipient = CampaignRecipient::with('campaign')->find($this->recipientId);

        if (! $recipient || $recipient->status !== CampaignRecipientStatus::Pending) {
            return;
        }

        $campaign = $recipient->campaign;

        if ($campaign->status === CampaignStatus::Paused) {
            $recipient->update(['queued_at' => null]);

            return;
        }

        if ($campaign->status !== CampaignStatus::Sending) {
            return;
        }

        // Unsubscribed after the campaign was created but before their batch.
        if (CampaignUnsubscribe::isUnsubscribed($campaign->channel, $recipient->address)) {
            $recipient->update(['status' => CampaignRecipientStatus::Cancelled, 'error' => 'Unsubscribed before this was sent.']);
            $campaign->refreshProgress();

            return;
        }

        $message = CampaignService::personalize($campaign->message, $recipient);

        try {
            if ($campaign->channel === CampaignChannel::Email) {
                $mailer->send($recipient->address, $mailer->compose(
                    CampaignService::personalize((string) $campaign->subject, $recipient),
                    $message,
                    $recipient->tracking_token,
                    (bool) $campaign->sendOption('include_signature'),
                    (bool) $campaign->sendOption('track_opens'),
                    $campaign->mailAttachments(),
                ));
            } else {
                $result = $sms->send($recipient->address, $message);
                $recipient->provider_message_id = $result['message_id'];
            }

            $recipient->status = CampaignRecipientStatus::Sent;
            $recipient->sent_at = now();
            $recipient->error = null;
        } catch (Throwable $e) {
            $recipient->status = CampaignRecipientStatus::Failed;
            $recipient->error = $e->getMessage();
        }

        $recipient->save();
        $campaign->refreshProgress();
    }

    /**
     * Worker killed / timed out mid-send — don't leave the row stuck on "pending".
     */
    public function failed(?Throwable $e): void
    {
        $recipient = CampaignRecipient::with('campaign')->find($this->recipientId);

        if ($recipient?->status === CampaignRecipientStatus::Pending) {
            $recipient->update(['status' => CampaignRecipientStatus::Failed, 'error' => $e?->getMessage() ?? 'Job failed.']);
            $recipient->campaign->refreshProgress();
        }
    }
}
