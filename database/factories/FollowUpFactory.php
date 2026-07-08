<?php

namespace Database\Factories;

use App\Enums\FollowUpStatus;
use App\Enums\ReminderType;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUp>
 */
class FollowUpFactory extends Factory
{
    protected $model = FollowUp::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'follow_up_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'follow_up_time' => fake()->time('H:i:s'),
            'reminder_minutes_before' => fake()->randomElement([15, 30, 60, 120]),
            'reminder_type' => fake()->randomElement(ReminderType::cases())->value,
            'status' => FollowUpStatus::Pending->value,
            'notified_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FollowUpStatus::Completed->value,
            'follow_up_date' => fake()->dateTimeBetween('-2 months', '-1 day')->format('Y-m-d'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FollowUpStatus::Cancelled->value,
            'follow_up_date' => fake()->dateTimeBetween('-2 months', '-1 day')->format('Y-m-d'),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'follow_up_date' => fake()->dateTimeBetween('-2 months', '-1 day')->format('Y-m-d'),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'follow_up_date' => fake()->dateTimeBetween('tomorrow', '+2 months')->format('Y-m-d'),
        ]);
    }
}
