<?php

namespace App\Notifications;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * To Super Admins: a campaign is waiting for their approval.
 */
class CampaignAwaitingApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(protected Campaign $campaign)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->campaign->creator?->name} submitted the {$this->campaign->channel->label()} campaign \"{$this->campaign->name}\" ({$this->campaign->recipient_count} recipients) for your approval",
            'campaign_id' => $this->campaign->id,
            'url' => route('campaigns.show', $this->campaign),
        ];
    }
}
