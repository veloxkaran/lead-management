<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Lead;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'plan_name' => fake()->randomElement(['Starter', 'Growth', 'Enterprise']),
            'status' => SubscriptionStatus::Trial->value,
            'contract_start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'licensed_users' => fake()->numberBetween(1, 50),
            'active_users' => fake()->numberBetween(1, 50),
            'billing_cycle' => BillingCycle::Monthly->value,
            'renewal_amount' => fake()->randomFloat(2, 100, 10000),
            'auto_renew' => false,
            'last_payment_date' => null,
            'outstanding_amount' => 0,
        ];
    }
}
