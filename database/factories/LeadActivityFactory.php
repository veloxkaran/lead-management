<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadActivity>
 */
class LeadActivityFactory extends Factory
{
    protected $model = LeadActivity::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'activity_type' => fake()->randomElement(ActivityType::cases())->value,
            'activity_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'activity_time' => fake()->time('H:i:s'),
            'description' => fake()->sentence(10),
            'created_by' => User::factory(),
        ];
    }
}
