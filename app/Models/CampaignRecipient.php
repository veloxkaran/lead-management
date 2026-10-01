<?php

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
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
