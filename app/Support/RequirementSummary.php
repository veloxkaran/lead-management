<?php

namespace App\Support;

use App\Enums\RequirementStatus;
use App\Models\Requirement;
use Illuminate\Support\Collection;

/**
 * Headline counts for a set of requirements — org-wide on the Requirements
 * list, one company's on its requirement page, and per company row for the
 * progress bar — so every place agrees on what "open" and "overdue" mean.
 */
class RequirementSummary
{
    public function __construct(
        public readonly int $total,
        public readonly int $open,
        public readonly int $overdue,
        public readonly int $completed,
        public readonly ?string $avgSolvingTime,
    ) {}

    /**
     * @param  Collection<int, Requirement>  $requirements
     */
    public static function of(Collection $requirements): self
    {
        $completed = $requirements->filter(fn (Requirement $r) => $r->status === RequirementStatus::Completed)->count();

        return new self(
            total: $requirements->count(),
            open: $requirements->count() - $completed,
            overdue: $requirements->filter(fn (Requirement $r) => $r->isOverdue())->count(),
            completed: $completed,
            avgSolvingTime: Requirement::averageResolutionFormattedAmong($requirements),
        );
    }

    public function completionPercent(): int
    {
        return $this->total === 0 ? 0 : (int) round($this->completed / $this->total * 100);
    }
}
