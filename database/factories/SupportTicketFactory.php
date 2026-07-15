<?php

namespace Database\Factories;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'lead_id' => null,
            'subject' => fake()->sentence(4),
            'details' => fake()->paragraph(),
            'priority' => RequirementPriority::Medium->value,
            'status' => RequirementStatus::Pending->value,
            'raised_by' => User::factory(),
            'assigned_to' => null,
        ];
    }
}
