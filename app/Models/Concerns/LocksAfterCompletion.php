<?php

namespace App\Models\Concerns;

use App\Enums\RequirementStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Completed requirements/support tickets stay editable for a short grace
 * window (to fix a mistaken status or last detail), then lock read-only —
 * for everyone except Super Admin, who can still fix or reopen them.
 * Reopening clears the completion timestamp, which lifts the lock.
 *
 * Relies on TracksResolutionTime::resolvedAtColumn() for the timestamp.
 */
trait LocksAfterCompletion
{
    public const EDIT_WINDOW_HOURS = 4;

    abstract protected function resolvedAtColumn(): string;

    /**
     * When the record locks — null while it isn't completed. Falls back to
     * updated_at for legacy rows completed before the timestamp existed.
     */
    public function locksAt(): ?Carbon
    {
        if ($this->status !== RequirementStatus::Completed) {
            return null;
        }

        $completedAt = $this->{$this->resolvedAtColumn()} ?? $this->updated_at;

        return $completedAt?->copy()->addHours(self::EDIT_WINDOW_HOURS);
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
