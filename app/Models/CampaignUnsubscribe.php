<?php

namespace App\Models;

use App\Enums\CampaignChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An address that asked to stop receiving campaigns. Checked when a
 * recipient list is built and again right before each message is sent.
 */
class CampaignUnsubscribe extends Model
{
    protected $fillable = ['channel', 'address', 'campaign_id', 'reason'];

    protected function casts(): array
    {
        return ['channel' => CampaignChannel::class];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public static function isUnsubscribed(CampaignChannel $channel, string $address): bool
    {
        return static::where('channel', $channel)->where('address', $address)->exists();
    }
}
