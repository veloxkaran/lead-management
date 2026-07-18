<?php

namespace App\Services;

use App\Enums\ContributionType;
use App\Enums\GoalCategory;
use App\Models\DealClosure;
use App\Models\Goal;
use App\Models\GoalContribution;

/**
 * Drives Organization Goal achievement from real, per-person business
 * events — today, only DealClosure ("the employee who closes and signs the
 * deal"). Goals are fully recomputed from the GoalContribution ledger (not
 * incremented/decremented), same philosophy the old Lead-based engine used:
 * a deal's value, closer, or date can change freely without any drift risk.
 */
class GoalContributionService
{
    /**
     * Called after a lead's deal is closed (or re-closed/corrected — the
     * unique index on (goal_id, source_type, source_id) makes this an
     * upsert, not a duplicate). Credits every deal-driven goal whose period
     * covers the deal's closed_date.
     */
    public function recordForDealClosure(DealClosure $deal): void
    {
        $goals = Goal::query()
            ->whereIn('category', array_map(fn (GoalCategory $c) => $c->value, $this->dealDrivenCategories()))
            ->whereDate('start_date', '<=', $deal->closed_date)
            ->whereDate('end_date', '>=', $deal->closed_date)
            ->get();

        foreach ($goals as $goal) {
            $this->upsertContributionForGoal($goal, $deal);
            $this->recalculateGoal($goal);
        }
    }

    /**
     * Called when a goal's own category or date range changes — re-scans
     * every DealClosure that now qualifies (not just previously-recorded
     * contributions), so widening a date range picks up older deals and
     * narrowing one drops contributions that no longer fit.
     */
    public function resyncGoal(Goal $goal): void
    {
        if (! $goal->category->isDealDriven()) {
            return;
        }

        $qualifyingDealIds = DealClosure::query()
            ->whereDate('closed_date', '>=', $goal->start_date)
            ->whereDate('closed_date', '<=', $goal->end_date)
            ->pluck('id');

        GoalContribution::where('goal_id', $goal->id)
            ->where('source_type', DealClosure::class)
            ->whereNotIn('source_id', $qualifyingDealIds)
            ->delete();

        DealClosure::whereIn('id', $qualifyingDealIds)->get()
            ->each(fn (DealClosure $deal) => $this->upsertContributionForGoal($goal, $deal));

        $this->recalculateGoal($goal);
    }

    public function recalculateGoal(Goal $goal): void
    {
        $contributions = GoalContribution::where('goal_id', $goal->id);

        $achieved = $goal->category->aggregatesByCount()
            ? $contributions->count()
            : (float) $contributions->sum('amount');

        if ((float) $goal->achieved !== (float) $achieved) {
            $goal->update(['achieved' => $achieved]);
        }
    }

    private function upsertContributionForGoal(Goal $goal, DealClosure $deal): void
    {
        GoalContribution::updateOrCreate(
            ['goal_id' => $goal->id, 'source_type' => DealClosure::class, 'source_id' => $deal->id],
            [
                'user_id' => $deal->closed_by,
                'company_id' => $deal->company_id,
                'contribution_type' => ContributionType::DealClosed,
                'amount' => $deal->deal_value,
                'contributed_at' => $deal->closed_date,
            ],
        );
    }

    /**
     * @return array<int, GoalCategory>
     */
    private function dealDrivenCategories(): array
    {
        return array_values(array_filter(GoalCategory::cases(), fn (GoalCategory $c) => $c->isDealDriven()));
    }
}
