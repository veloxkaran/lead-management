<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'plan' => 'standard',
            'status' => 'active',
            'timezone' => 'UTC',
            'locale' => 'en',
            'fiscal_calendar' => 'gregorian',
        ];
    }
}
