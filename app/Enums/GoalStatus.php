<?php

namespace App\Enums;

enum GoalStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Completed = 'completed';
    case Expired = 'expired';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Upcoming => 'bg-info-subtle text-info-emphasis',
            self::Active => 'bg-primary-subtle text-primary-emphasis',
            self::Completed => 'bg-success-subtle text-success-emphasis',
            self::Expired => 'bg-secondary-subtle text-secondary-emphasis',
        };
    }
}
