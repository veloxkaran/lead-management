<?php

namespace Database\Factories;

use App\Enums\TaskModule;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'module' => TaskModule::InternalOperations->value,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Pending->value,
            'created_by' => User::factory(),
        ];
    }
}
