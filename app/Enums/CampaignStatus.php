<?php

namespace App\Enums;

/**
 * The campaign as a whole. Campaigns from anyone but a Super Admin start
 * AwaitingApproval; a Super Admin approves them (→ Pending, then Sending)
 * or rejects them. Per-recipient progress (pending → sent → delivered /
 * failed) lives on CampaignRecipientStatus.
 */
enum CampaignStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
    case Rejected = 'rejected';
    case Pending = 'pending';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingApproval => 'Awaiting Approval',
            self::Rejected => 'Rejected',
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
            self::AwaitingApproval => 'bg-warning text-dark',
            self::Rejected => 'bg-danger-subtle text-danger-emphasis',
            self::Pending => 'bg-warning-subtle text-warning-emphasis',
            self::Sending => 'bg-info-subtle text-info-emphasis',
            self::Paused => 'bg-dark-subtle text-dark-emphasis',
            self::Completed => 'bg-success-subtle text-success-emphasis',
            self::Cancelled => 'bg-secondary-subtle text-secondary-emphasis',
        };
    }
}
