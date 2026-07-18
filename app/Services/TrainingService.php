<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\LeadActivity;
use App\Models\Training;
use App\Models\User;
use App\Repositories\TrainingRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class TrainingService
{
    public function __construct(protected TrainingRepository $trainings)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->trainings->filter($filters, $perPage);
    }

    public function create(array $attributes, User $actor): Training
    {
        /** @var Training $training */
        $training = $this->trainings->create($attributes);

        $this->logActivity($training, $actor, "Training scheduled for {$training->lead?->company_name} by {$actor->name}.");

        return $training;
    }

    public function update(Training $training, array $attributes, User $actor): Training
    {
        $training = $this->trainings->update($training, $attributes);

        $this->logActivity($training, $actor, "Training updated by {$actor->name} — status: {$training->status->label()}.");

        return $training;
    }

    public function delete(Training $training): void
    {
        $this->trainings->delete($training);
    }

    private function logActivity(Training $training, User $actor, string $description): void
    {
        LeadActivity::create([
            'lead_id' => $training->lead_id,
            'activity_type' => ActivityType::TrainingUpdate,
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $description,
            'created_by' => $actor->id,
        ]);
    }
}
