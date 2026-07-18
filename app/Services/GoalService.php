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
        protected GoalContributionService $goalContributions,
    ) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->goals->filter($filters, $perPage);
    }

    public function create(array $attributes, User $creator): Goal
    {
        return DB::transaction(function () use ($attributes, $creator) {
            $attributes['created_by'] = $creator->id;

            $goal = $this->goals->create($attributes);

            $this->goalContributions->resyncGoal($goal);

            return $goal;
        });
    }

    public function update(Goal $goal, array $attributes): Goal
    {
        $goal = $this->goals->update($goal, $attributes);

        $this->goalContributions->resyncGoal($goal);

        return $goal;
    }

    public function delete(Goal $goal): bool
    {
        return $this->goals->delete($goal);
    }
}
