<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadProgressStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_development_owner_sees_the_lead_but_not_the_progress_cards(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $bde->id, 'created_by' => $bde->id]);

        $response = $this->actingAs($bde)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertDontSeeText('Training Status');
    }

    public function test_customer_success_sees_the_progress_cards(): void
    {
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $bde->id, 'created_by' => $bde->id]);

        $response = $this->actingAs($cs)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSeeText('Training Status');
    }

    public function test_reporting_line_manager_of_the_assigned_user_passes_the_policy_read_only(): void
    {
        $ancestorManager = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $bde = User::factory()->create([
            'role' => UserRole::BusinessDevelopment,
            'reporting_manager_id' => $ancestorManager->id,
        ]);
        $lead = Lead::factory()->create(['assigned_user_id' => $bde->id, 'created_by' => $bde->id]);
        $training = Training::factory()->create(['lead_id' => $lead->id]);

        // Reaching the Lead page itself is gated by the separate, pre-existing
        // view() policy (owner/overseer/handoff) which this feature doesn't
        // change — so this is asserted at the policy level directly rather
        // than via the full HTTP page.
        $this->assertTrue($ancestorManager->can('viewProgressStatus', $lead));

        // Read-only: the ancestor manager passes viewProgressStatus but is
        // neither Manager/CustomerSuccess/SuperAdmin, so TrainingPolicy::update
        // fails and the per-record edit route stays forbidden.
        $this->actingAs($ancestorManager)->get(route('trainings.edit', $training))->assertForbidden();
    }

    public function test_unrelated_business_development_user_does_not_see_the_cards(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $otherBde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $otherBde->id, 'created_by' => $otherBde->id]);

        // $bde reaches the lead page only via the CS/Finance handoff rule, so
        // for this assertion we instead confirm the policy directly: an
        // unrelated BD rep with no reporting-line tie to $otherBde is denied.
        $this->assertFalse($bde->can('viewProgressStatus', $lead));
    }

    public function test_lead_show_query_count_does_not_grow_with_progress_history_size(): void
    {
        // Two separate viewers, each visiting for the first time, so the
        // measurement isolates the effect of history size rather than any
        // per-user first-visit side effect.
        $csA = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $csB = User::factory()->create(['role' => UserRole::CustomerSuccess]);

        $leadA = Lead::factory()->create();
        Training::factory()->create(['lead_id' => $leadA->id]);

        $leadB = Lead::factory()->create();
        Training::factory()->count(5)->create(['lead_id' => $leadB->id]);

        DB::enableQueryLog();
        $this->actingAs($csA)->get(route('leads.show', $leadA))->assertOk();
        $queryCountForOne = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->actingAs($csB)->get(route('leads.show', $leadB))->assertOk();
        $queryCountForFive = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForOne, $queryCountForFive);
    }
}
