<?php

namespace App\Support;

use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use Illuminate\Support\Collection;

/**
 * Headline counts for the Support Tickets list — the same strip the
 * Requirements list has (see RequirementSummary): all / open / overdue /
 * completed, % done, and the average time from raised to resolved.
 */
class SupportTicketSummary
{
    public function __construct(
        public readonly int $total,
        public readonly int $open,
        public readonly int $overdue,
        public readonly int $completed,
        public readonly ?string $avgResolutionTime,
    ) {}

    /**
     * @param  Collection<int, SupportTicket>  $tickets
     */
    public static function of(Collection $tickets): self
    {
        $completed = $tickets->filter(fn (SupportTicket $t) => $t->status === RequirementStatus::Completed)->count();

        return new self(
            total: $tickets->count(),
            open: $tickets->count() - $completed,
            overdue: $tickets->filter(fn (SupportTicket $t) => $t->isOverdue())->count(),
            completed: $completed,
            avgResolutionTime: SupportTicket::averageResolutionFormattedAmong($tickets),
        );
    }

    public function completionPercent(): int
    {
        return $this->total === 0 ? 0 : (int) round($this->completed / $this->total * 100);
    }
}
