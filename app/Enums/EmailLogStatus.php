<?php

namespace App\Enums;

enum EmailLogStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Sent => 'bg-success-subtle text-success-emphasis',
            self::Failed => 'bg-danger-subtle text-danger-emphasis',
        };
    }
}
