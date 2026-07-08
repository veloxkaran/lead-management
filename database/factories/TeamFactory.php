<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Team',
            'description' => fake()->sentence(),
        ];
    }
}
