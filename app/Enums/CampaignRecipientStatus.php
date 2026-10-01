<?php

namespace App\Enums;

/**
 * Sent = the mail server / SMS gateway accepted the message. Delivered =
 * confirmed at the other end: an SMS gateway delivery report, or (email)
 * the recipient's mail app loading the tracking image — plain SMTP has no
 * delivery receipt, so an opened email is the only confirmation there is.
 */
enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis',
            self::Sent => 'bg-primary-subtle text-primary-emphasis',
            self::Delivered => 'bg-success-subtle text-success-emphasis',
            self::Failed => 'bg-danger-subtle text-danger-emphasis',
            self::Cancelled => 'bg-secondary-subtle text-secondary-emphasis',
        };
    }
}
