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
     * Filter goals, scoping visibility for non-super-admin viewers to:
     * all Organization goals, their own Team's goals, and their own Individual goals.
     */
    public function filter(array $filters, ?User $viewer = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()->with(['team', 'user', 'creator']);

        if ($viewer && ! $viewer->isSuperAdmin()) {
            $query->where(function ($q) use ($viewer) {
                $q->where('goal_type', GoalType::Organization->value)
                    ->orWhere(function ($q2) use ($viewer) {
                        $q2->where('goal_type', GoalType::Team->value)
                            ->where('team_id', $viewer->team_id);
                    })
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
