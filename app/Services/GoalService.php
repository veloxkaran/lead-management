<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\User;
use App\Repositories\GoalRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GoalService
{
    public function __construct(
        protected GoalRepository $goals,
        protected GoalAchievementService $goalAchievements,
    ) {
    }

    public function list(array $filters, ?User $viewer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->goals->filter($filters, $viewer, $perPage);
    }

    public function create(array $attributes, User $creator): Goal
    {
        return DB::transaction(function () use ($attributes, $creator) {
            $attributes = $this->clearIrrelevantTargets($attributes);
            $attributes['created_by'] = $creator->id;

            $goal = $this->goals->create($attributes);

            $this->goalAchievements->recalculate($goal);

            return $goal;
        });
    }

    public function update(Goal $goal, array $attributes): Goal
    {
        $attributes = $this->clearIrrelevantTargets($attributes);

        $goal = $this->goals->update($goal, $attributes);

        $this->goalAchievements->recalculate($goal);

        return $goal;
    }

    public function delete(Goal $goal): bool
    {
        return $this->goals->delete($goal);
    }

    protected function clearIrrelevantTargets(array $attributes): array
    {
        $type = $attributes['goal_type'] ?? null;
        $type = $type instanceof \App\Enums\GoalType ? $type->value : $type;

        if ($type !== 'team') {
            $attributes['team_id'] = null;
        }

        if ($type !== 'individual') {
            $attributes['user_id'] = null;
        }

        return $attributes;
    }
}
