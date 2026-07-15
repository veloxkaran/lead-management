<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\LeadStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadStatusHistory>
 */
class LeadStatusHistoryFactory extends Factory
{
    protected $model = LeadStatusHistory::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'from_status_id' => null,
            'to_status_id' => LeadStatus::factory(),
            'changed_by' => User::factory(),
            'changed_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'seconds_in_previous_status' => null,
        ];
    }
}
