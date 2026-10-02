<?php

namespace App\Enums;

/**
 * Sent = the mail server accepted it. Delivered = the recipient's mail app
 * loaded the email's tracking image (opened it) — plain SMTP gives no
 * delivery receipt, so an open is the only confirmation there is.
 */
enum EmailLogStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis',
            self::Sent => 'bg-primary-subtle text-primary-emphasis',
            self::Delivered => 'bg-success-subtle text-success-emphasis',
            self::Failed => 'bg-danger-subtle text-danger-emphasis',
        };
    }
}
