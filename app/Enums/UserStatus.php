<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-success',
            self::Inactive => 'bg-secondary',
            self::Suspended => 'bg-danger',
        };
    }
}
