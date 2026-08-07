<?php

namespace App\Enums;

enum SupportTicketAssignmentAction: string
{
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::Unassigned => 'Unassigned',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Assigned => 'bg-success-subtle text-success-emphasis',
            self::Unassigned => 'bg-secondary-subtle text-secondary-emphasis',
        };
    }
}
