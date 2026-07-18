<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Assigned => 'Assigned',
            self::InProgress => 'In Progress',
            self::Waiting => 'Waiting',
            self::OnHold => 'On Hold',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::Assigned => 'bg-info text-dark',
            self::InProgress => 'bg-primary',
            self::Waiting => 'bg-light text-dark border',
            self::OnHold => 'bg-warning text-dark',
            self::Completed => 'bg-success',
            self::Cancelled => 'bg-danger',
        };
    }
}
