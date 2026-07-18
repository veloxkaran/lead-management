<?php

namespace App\Repositories;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class GoalRepository extends BaseRepository
{
    public function __construct(Goal $model)
    {
        parent::__construct($model);
    }

    /**
     * Filter goals, scoping visibility for non-overseer viewers to:
     * all Organization goals, and their own Individual goals.
     */
    public function filter(array $filters, ?User $viewer = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()->with(['user', 'creator']);

        if ($viewer && ! $viewer->isOverseer()) {
            $query->where(function ($q) use ($viewer) {
                $q->where('goal_type', GoalType::Organization->value)
                    ->orWhere(function ($q3) use ($viewer) {
                        $q3->where('goal_type', GoalType::Individual->value)
                            ->where('user_id', $viewer->id);
                    });
            });
        }

        if (! empty($filters['goal_type'])) {
            $query->where('goal_type', $filters['goal_type']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
