<?php

namespace Database\Factories;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requirement>
 */
class RequirementFactory extends Factory
{
    protected $model = Requirement::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'requirement' => fake()->sentence(12),
            'priority' => fake()->randomElement(RequirementPriority::cases())->value,
            'status' => fake()->randomElement(RequirementStatus::cases())->value,
            'assigned_to' => null,
            'created_by' => User::factory(),
        ];
    }
}
