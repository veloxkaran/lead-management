<?php

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
use App\Support\EmailFailureReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id', 'batch', 'lead_id', 'contact_id', 'name', 'company_name', 'address', 'status',
        'provider_message_id', 'tracking_token', 'error', 'queued_at', 'sent_at', 'delivered_at', 'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignRecipientStatus::class,
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * What this recipient's status means, in plain words — the Remarks
     * column of the send log and its CSV export.
     */
    public function remarks(bool $isEmail = true): string
    {
        $remark = match ($this->status) {
            CampaignRecipientStatus::Pending => $this->queued_at
                ? 'Queued '.$this->queued_at->diffForHumans().' — going out now.'
                : 'Waiting for batch '.($this->batch ?? '—').'.',
            CampaignRecipientStatus::Sent => $isEmail
                ? 'Accepted by the mail server. Not opened yet — or opened with images blocked, which can\'t be detected.'
                : 'Accepted by the SMS gateway; no delivery report yet.',
            CampaignRecipientStatus::Delivered => $isEmail
                ? 'Opened by the recipient'.($this->delivered_at ? ' on '.$this->delivered_at->format('M d, g:i A') : '').'.'
                : 'Delivered — confirmed by the gateway'.($this->delivered_at ? ' on '.$this->delivered_at->format('M d, g:i A') : '').'.',
            CampaignRecipientStatus::Failed => EmailFailureReason::explain($this->error) ?? 'Couldn\'t be sent — see the error below.',
            CampaignRecipientStatus::Cancelled => $this->error ?: 'Not sent — the campaign was cancelled or rejected.',
        };

        return $this->unsubscribed_at ? $remark.' Unsubscribed on '.$this->unsubscribed_at->format('M d, Y').'.' : $remark;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }
}
