<?php

namespace App\Enums;

enum RawDataStatus: string
{
    case New = 'new';
    case NotValid = 'not_valid';
    case ConvertedToLead = 'converted_to_lead';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::NotValid => 'Not Valid',
            self::ConvertedToLead => 'Converted to Lead',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'bg-primary',
            self::NotValid => 'bg-danger',
            self::ConvertedToLead => 'bg-success',
        };
    }

    public function isFinalized(): bool
    {
        return $this !== self::New;
    }
}
