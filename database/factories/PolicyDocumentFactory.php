<?php

namespace Database\Factories;

use App\Enums\PolicyDocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PolicyDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => PolicyDocumentType::Sop,
            'title' => fake()->sentence(3),
            'allow_skip' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function individualJd(): static
    {
        return $this->state(fn () => [
            'type' => PolicyDocumentType::IndividualJd,
            'user_id' => User::factory(),
        ]);
    }
}
