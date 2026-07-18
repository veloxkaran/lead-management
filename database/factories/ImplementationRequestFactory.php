<?php

namespace Database\Factories;

use App\Enums\ImplementationStatus;
use App\Models\ImplementationRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImplementationRequest>
 */
class ImplementationRequestFactory extends Factory
{
    protected $model = ImplementationRequest::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'title' => fake()->sentence(4),
            'details' => fake()->paragraph(),
            'status' => ImplementationStatus::NotStarted->value,
            'requested_by' => User::factory(),
            'assigned_to' => null,
            'planned_date' => null,
            'completion_percentage' => 0,
            'phase' => null,
            'notes' => null,
        ];
    }
}
