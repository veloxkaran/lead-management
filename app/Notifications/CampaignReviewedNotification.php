<?php

namespace App\Notifications;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * To the campaign's creator: a Super Admin approved or rejected it.
 */
class CampaignReviewedNotification extends Notification
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
        $reviewer = $this->campaign->reviewer?->name ?? 'A Super Admin';

        return [
            'message' => $this->campaign->status === CampaignStatus::Rejected
                ? "{$reviewer} rejected your campaign \"{$this->campaign->name}\": {$this->campaign->review_note}"
                : "{$reviewer} approved your campaign \"{$this->campaign->name}\" — ".($this->campaign->scheduled_at?->isFuture() ? 'it sends on '.$this->campaign->scheduled_at->format('M d, g:i A').'.' : 'it is sending now.'),
            'campaign_id' => $this->campaign->id,
            'url' => route('campaigns.show', $this->campaign),
        ];
    }
}
