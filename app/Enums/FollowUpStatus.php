<?php

namespace App\Enums;

enum FollowUpStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning text-dark',
            self::Sent => 'bg-info text-dark',
            self::Completed => 'bg-success',
            self::Cancelled => 'bg-secondary',
        };
    }
}
