<?php

namespace App\Repositories;

use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GoalContributionRepository extends BaseRepository
{
    public function __construct(GoalContribution $model)
    {
        parent::__construct($model);
    }

    public function forGoal(Goal $goal, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->where('goal_id', $goal->id)
            ->with(['user', 'source.lead'])
            ->latest('contributed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function forUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->where('user_id', $user->id)
            ->with(['goal', 'source.lead'])
            ->latest('contributed_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Ranked by total contribution — a single row per user, aggregated
     * across every contribution matching the filters. Mirrors the
     * selectRaw()+groupBy() aggregate idiom already used by
     * OrganizationHierarchyService::getTeamStatistics() for DealClosure.
     *
     * @param  array{goal_id?: int, company_id?: int, date_from?: string, date_to?: string, user_ids?: array<int, int>}  $filters
     */
    public function leaderboard(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()
            ->selectRaw('user_id, SUM(amount) as total_amount, COUNT(*) as deals_count')
            ->groupBy('user_id')
            ->with('user');

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('total_amount')
            ->paginate($perPage, ['*'], 'page')
            ->withQueryString();
    }

    /**
     * The filtered set's grand total — needed for each leaderboard row's
     * "% of total" column, which a single page of ranked rows can't derive
     * from itself.
     */
    public function totalFor(array $filters): float
    {
        $query = $this->query();
        $this->applyFilters($query, $filters);

        return (float) $query->sum('amount');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['goal_id'])) {
            $query->where('goal_id', $filters['goal_id']);
        }

        if (! empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('contributed_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('contributed_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['user_ids'])) {
            $query->whereIn('user_id', $filters['user_ids']);
        }
    }
}
