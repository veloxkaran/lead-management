<?php

namespace Database\Factories;

use App\Enums\ActivityModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module' => ActivityModule::Lead,
            'description' => fake()->sentence(),
        ];
    }
}
