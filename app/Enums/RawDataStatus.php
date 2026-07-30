<?php

namespace App\Enums;

enum RawDataStatus: string
{
    case New = 'new';
    case Hold = 'hold';
    case NotValid = 'not_valid';
    case ConvertedToLead = 'converted_to_lead';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Hold => 'Hold',
            self::NotValid => 'Not Valid',
            self::ConvertedToLead => 'Converted to Lead',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'bg-primary',
            self::Hold => 'bg-warning text-dark',
            self::NotValid => 'bg-danger',
            self::ConvertedToLead => 'bg-success',
        };
    }

    /**
     * Not Valid and Converted to Lead are dead ends the service layer
     * refuses to transition out of — New and Hold both stay actionable
     * (assignable, convertible, markable) since Hold is just a pause, not
     * a resolution.
     */
    public function isFinalized(): bool
    {
        return $this === self::NotValid || $this === self::ConvertedToLead;
    }
}
