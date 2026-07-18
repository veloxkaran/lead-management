<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Low => 'bg-secondary',
            self::Medium => 'bg-info text-dark',
            self::High => 'bg-warning text-dark',
            self::Critical => 'bg-danger',
        };
    }
}
