<?php

namespace App\Enums;

enum TrainingStatus: string
{
    case NotScheduled = 'not_scheduled';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotScheduled => 'Not Scheduled',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotScheduled => 'bg-secondary',
            self::Scheduled => 'bg-info text-dark',
            self::InProgress => 'bg-primary',
            self::Completed => 'bg-success',
            self::Cancelled => 'bg-danger',
        };
    }
}
