<?php

namespace App\Models\Concerns;

use App\Enums\RequirementStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Completed (or Cancelled) requirements/support tickets stay editable for a
 * short grace window (to fix a mistaken status or last detail), then lock
 * read-only — for everyone except Super Admin, who can still fix or reopen
 * them. Reopening clears the completion/cancellation timestamp, which lifts
 * the lock.
 *
 * Relies on TracksResolutionTime::resolvedAtColumn() for the completion
 * timestamp and Cancellable's cancelled_at for cancellations.
 */
trait LocksAfterCompletion
{
    public const EDIT_WINDOW_HOURS = 4;

    abstract protected function resolvedAtColumn(): string;

    /**
     * When the record locks — null while it's still open. Falls back to
     * updated_at for legacy rows closed before the timestamp existed.
     */
    public function locksAt(): ?Carbon
    {
        $closedAt = match ($this->status) {
            RequirementStatus::Completed => $this->{$this->resolvedAtColumn()},
            RequirementStatus::Cancelled => $this->cancelled_at,
            default => false,
        };

        if ($closedAt === false) {
            return null;
        }

        return ($closedAt ?? $this->updated_at)?->copy()->addHours(self::EDIT_WINDOW_HOURS);
    }

    public function isLocked(): bool
    {
        return $this->locksAt()?->isPast() ?? false;
    }

    public function isLockedFor(User $user): bool
    {
        return ! $user->isSuperAdmin() && $this->isLocked();
    }
}
