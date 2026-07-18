<?php

namespace Database\Factories;

use App\Enums\TrainingStatus;
use App\Models\Lead;
use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    protected $model = Training::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'status' => TrainingStatus::NotScheduled->value,
            'training_date' => null,
            'trainer_name' => fake()->name(),
            'attendees_count' => null,
            'department_id' => null,
            'completion_percentage' => 0,
            'feedback' => null,
            'conducted_by' => null,
        ];
    }
}
