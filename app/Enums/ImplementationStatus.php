<?php

namespace App\Enums;

enum ImplementationStatus: string
{
    case NotStarted = 'not_started';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::OnHold => 'On Hold',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotStarted => 'bg-secondary',
            self::Scheduled => 'bg-info text-dark',
            self::InProgress => 'bg-primary',
            self::OnHold => 'bg-warning text-dark',
            self::Completed => 'bg-success',
            self::Cancelled => 'bg-danger',
        };
    }
}
