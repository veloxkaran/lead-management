<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_update_a_subscription(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $lead = Lead::factory()->create();

        $this->actingAs($manager)->post(route('subscriptions.store'), [
            'lead_id' => $lead->id,
            'plan_name' => 'Growth',
            'billing_cycle' => 'monthly',
            'expiry_date' => now()->addDays(10)->toDateString(),
            'renewal_amount' => 500,
        ])->assertRedirect(route('leads.show', $lead));

        $subscription = Subscription::firstWhere('lead_id', $lead->id);
        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->isExpiringSoon());

        $this->actingAs($manager)->put(route('subscriptions.update', $subscription), [
            'plan_name' => 'Enterprise',
            'status' => 'active',
            'billing_cycle' => 'yearly',
            'renewal_amount' => 5000,
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_name' => 'Enterprise',
            'status' => 'active',
        ]);
    }

    public function test_finance_cannot_manage_subscriptions(): void
    {
        $finance = User::factory()->create(['role' => UserRole::Finance]);

        $this->actingAs($finance)->get(route('subscriptions.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('subscriptions.create'))->assertForbidden();
    }

    public function test_days_remaining_and_expiring_soon_are_computed_not_stored(): void
    {
        $expiringSoon = Subscription::factory()->make(['expiry_date' => now()->addDays(5)]);
        $farOut = Subscription::factory()->make(['expiry_date' => now()->addDays(90)]);
        $expired = Subscription::factory()->make(['expiry_date' => now()->subDays(3)]);

        $this->assertTrue($expiringSoon->isExpiringSoon());
        $this->assertFalse($farOut->isExpiringSoon());
        $this->assertFalse($expired->isExpiringSoon());
    }
}
