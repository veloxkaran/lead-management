<?php

namespace App\Enums;

enum RequirementStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::OnHold => 'On Hold',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::InProgress => 'bg-primary',
            self::Completed => 'bg-success',
            self::OnHold => 'bg-warning text-dark',
        };
    }
}
