<?php

namespace App\Enums;

enum EmailLogStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis',
            self::Sent => 'bg-success-subtle text-success-emphasis',
            self::Failed => 'bg-danger-subtle text-danger-emphasis',
        };
    }
}
