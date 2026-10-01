<?php

namespace App\Enums;

/**
 * The campaign as a whole. Per-recipient progress (pending → sent →
 * delivered / failed) lives on CampaignRecipientStatus.
 */
enum CampaignStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sending => 'Sending',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis',
            self::Sending => 'bg-info-subtle text-info-emphasis',
            self::Paused => 'bg-dark-subtle text-dark-emphasis',
            self::Completed => 'bg-success-subtle text-success-emphasis',
            self::Cancelled => 'bg-secondary-subtle text-secondary-emphasis',
        };
    }
}
