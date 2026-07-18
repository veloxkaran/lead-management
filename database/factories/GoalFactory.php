<?php

namespace Database\Factories;

use App\Enums\GoalCategory;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', 'now');
        $end = (clone $start)->modify('+3 months');
        $target = fake()->randomFloat(2, 50000, 500000);

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'category' => fake()->randomElement(GoalCategory::cases()),
            'target' => $target,
            'achieved' => fake()->randomFloat(2, 0, $target),
            'start_date' => $start,
            'end_date' => $end,
            'bs_year' => null,
            'bs_month' => null,
            'created_by' => User::factory(),
        ];
    }
}
