<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Sales', 'Marketing', 'Engineering', 'Customer Success', 'Finance', 'HR']),
            'description' => fake()->sentence(),
        ];
    }
}
