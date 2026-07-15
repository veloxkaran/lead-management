<?php

namespace Database\Factories;

use App\Enums\PolicyDocumentType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PolicyDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => PolicyDocumentType::Sop,
            'title' => fake()->sentence(3),
            'department_id' => Department::factory(),
            'allow_skip' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function departmentJd(): static
    {
        return $this->state(fn () => ['type' => PolicyDocumentType::DepartmentJd]);
    }

    public function individualJd(): static
    {
        return $this->state(fn () => [
            'type' => PolicyDocumentType::IndividualJd,
            'department_id' => null,
            'user_id' => User::factory(),
        ]);
    }
}
