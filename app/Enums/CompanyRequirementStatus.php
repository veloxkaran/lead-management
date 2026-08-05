<?php

namespace App\Enums;

use App\Models\Requirement;

enum CompanyRequirementStatus: string
{
    case Pending = 'pending';
    case PartiallyProcessed = 'partially_processed';
    case Processed = 'processed';
    case PartiallyDone = 'partially_done';
    case Done = 'done';

    /**
     * Most-complete-first so a set of requirements that satisfies more than
     * one rule (e.g. "all done" also satisfies "none pending") lands on the
     * most informative label rather than the first rule it happens to match.
     *
     * @param  iterable<int, Requirement>  $requirements
     */
    public static function fromRequirements(iterable $requirements): self
    {
        $total = 0;
        $pending = 0;
        $done = 0;
        $other = 0;

        foreach ($requirements as $requirement) {
            $total++;

            match ($requirement->status) {
                RequirementStatus::Pending => $pending++,
                RequirementStatus::Completed => $done++,
                default => $other++, // in_progress, on_hold
            };
        }

        if ($total === 0 || $pending === $total) {
            return self::Pending;
        }

        if ($done === $total) {
            return self::Done;
        }

        if ($done > 0) {
            return self::PartiallyDone;
        }

        if ($pending === 0) {
            return self::Processed;
        }

        return self::PartiallyProcessed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PartiallyProcessed => 'Partially Processed',
            self::Processed => 'Processed',
            self::PartiallyDone => 'Partially Done',
            self::Done => 'Done',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::PartiallyProcessed => 'bg-info text-dark',
            self::Processed => 'bg-primary',
            self::PartiallyDone => 'bg-warning text-dark',
            self::Done => 'bg-success',
        };
    }
}
