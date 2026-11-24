<?php

namespace App\Enums;

/**
 * Shared by requirements and support tickets. Completed and Cancelled are
 * both "closed" — no longer open work, never overdue, not claimable — but
 * only Completed counts as solved (completed_at / resolved_at, solving-time
 * averages, % done). Cancelled always carries a reason (see Cancellable).
 */
enum RequirementStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::InReview => 'In Review',
            self::Completed => 'Completed',
            self::OnHold => 'On Hold',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::InProgress => 'bg-primary',
            self::InReview => 'bg-info text-dark',
            self::Completed => 'bg-success',
            self::OnHold => 'bg-warning text-dark',
            self::Cancelled => 'bg-danger',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, self::closed(), true);
    }

    /**
     * @return array<int, self>
     */
    public static function closed(): array
    {
        return [self::Completed, self::Cancelled];
    }

    /**
     * For whereNotIn('status', …) / whereIn('status', …) queries.
     *
     * @return array<int, string>
     */
    public static function closedValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::closed());
    }
}
