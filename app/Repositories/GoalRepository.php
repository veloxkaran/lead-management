<?php

namespace App\Repositories;

use App\Models\Goal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GoalRepository extends BaseRepository
{
    public function __construct(Goal $model)
    {
        parent::__construct($model);
    }

    /**
     * Every goal is visible to every employee (GoalPolicy::viewAny is
     * unconditionally true) — no viewer-based scoping needed, unlike the
     * old Individual/Organization split.
     */
    public function filter(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()->with('creator');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $this->applyStatusFilter($query, $filters['status']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * Mirrors Goal::status()'s derivation in SQL so pagination counts stay
     * correct (a PHP-side filter after paginate() would under/over-count).
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        $today = now()->toDateString();
        $notCompleted = fn ($q) => $q->where(fn ($q2) => $q2->where('target', '<=', 0)->orWhereColumn('achieved', '<', 'target'));

        match ($status) {
            'completed' => $query->where('target', '>', 0)->whereColumn('achieved', '>=', 'target'),
            'upcoming' => $query->where($notCompleted)->whereDate('start_date', '>', $today),
            'expired' => $query->where($notCompleted)->whereDate('start_date', '<=', $today)->whereDate('end_date', '<', $today),
            'active' => $query->where($notCompleted)->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today),
            default => null,
        };
    }
}
