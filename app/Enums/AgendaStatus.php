<?php

namespace App\Enums;

enum AgendaStatus: string
{
    case Pending = 'pending';
    case Closed = 'closed';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Closed => 'Closed',
            self::Dismissed => 'Dismissed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-primary',
            self::Closed => 'bg-success',
            self::Dismissed => 'bg-secondary',
        };
    }

    /**
     * The only legal transitions are Pending -> Closed and Pending ->
     * Dismissed. Once finalized (Closed or Dismissed), no transition is
     * ever legal again — the status is permanent by design.
     */
    public function canTransitionTo(self $target): bool
    {
        return $this === self::Pending && in_array($target, [self::Closed, self::Dismissed], true);
    }

    public function isFinalized(): bool
    {
        return $this !== self::Pending;
    }
}
