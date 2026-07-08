<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\DailySummary>
 */
class DailySummaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'summary_date' => fake()->unique()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'achieved_today' => fake()->sentence(12),
            'planned_tomorrow' => fake()->sentence(12),
            'blockers' => fake()->boolean(30) ? fake()->sentence(8) : null,
        ];
    }
}
