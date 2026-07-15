<?php

namespace Database\Factories;

use App\Models\DealClosure;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealClosure>
 */
class DealClosureFactory extends Factory
{
    protected $model = DealClosure::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'closed_by' => User::factory(),
            'closed_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'deal_value' => fake()->randomFloat(2, 500, 20000),
            'closing_comment' => fake()->sentence(8),
        ];
    }
}
