<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LeadStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fn (array $attrs) => \Illuminate\Support\Str::slug($attrs['name']),
            'color' => fake()->hexColor(),
            'order' => fake()->numberBetween(0, 20),
        ];
    }
}
