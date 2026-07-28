<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression test for the ValueError this app hit when a lead_activities
 * row logged before the Implementation Request/Subscription removal
 * (activity_type = 'implementation_request'/'subscription_update') was
 * still present — Eloquent's enum cast hard-fails reading a backing value
 * that no longer has a matching case, e.g. after a restored backup
 * predating the removal. Demonstrates the failure mode directly, then
 * confirms the same cleanup performed by
 * 2026_07_28_130000_remove_stale_implementation_and_subscription_lead_activities
 * resolves it.
 */
class StaleLeadActivityCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_activity_type_value_breaks_the_page_until_cleaned_up(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);

        DB::table('lead_activities')->insert([
            'lead_id' => $lead->id,
            'activity_type' => 'implementation_request',
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => 'Stale pre-removal entry',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('leads.show', $lead))->assertStatus(500);

        DB::table('lead_activities')
            ->whereIn('activity_type', ['implementation_request', 'subscription_update'])
            ->delete();

        $this->actingAs($user)->get(route('leads.show', $lead))->assertOk();
    }
}
