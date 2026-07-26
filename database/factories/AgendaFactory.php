<?php

namespace Database\Factories;

use App\Enums\AgendaStatus;
use App\Models\Agenda;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agenda>
 */
class AgendaFactory extends Factory
{
    protected $model = Agenda::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => AgendaStatus::Pending->value,
            'created_by' => User::factory(),
        ];
    }
}
