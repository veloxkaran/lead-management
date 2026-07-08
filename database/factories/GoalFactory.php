<?php

namespace Database\Factories;

use App\Enums\GoalType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Goal>
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
            'target' => $target,
            'achieved' => fake()->randomFloat(2, 0, $target),
            'goal_type' => GoalType::Individual,
            'team_id' => null,
            'user_id' => User::factory(),
            'start_date' => $start,
            'end_date' => $end,
            'bs_year' => null,
            'bs_month' => null,
            'created_by' => User::factory(),
        ];
    }

    public function organization(): static
    {
        return $this->state(fn () => [
            'goal_type' => GoalType::Organization,
            'team_id' => null,
            'user_id' => null,
        ]);
    }

    public function team(): static
    {
        return $this->state(fn () => [
            'goal_type' => GoalType::Team,
            'user_id' => null,
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn () => [
            'goal_type' => GoalType::Individual,
            'team_id' => null,
        ]);
    }
}
