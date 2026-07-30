<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadHierarchyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_senior_rep_sees_a_lead_created_by_their_junior_report_in_the_index(): void
    {
        $senior = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $junior = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $senior->id]);
        $lead = Lead::factory()->create(['assigned_user_id' => $junior->id, 'created_by' => $junior->id]);

        $response = $this->actingAs($senior)->get(route('leads.index', ['created_by' => '']));

        $response->assertOk();
        $response->assertViewHas('leads', fn ($leads) => $leads->total() === 1 && $leads->first()->is($lead));
    }

    public function test_a_senior_rep_can_view_and_update_a_lead_owned_by_an_indirect_report(): void
    {
        $senior = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $middle = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $senior->id]);
        $junior = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $middle->id]);
        $lead = Lead::factory()->create(['assigned_user_id' => $junior->id, 'created_by' => $junior->id]);

        $this->actingAs($senior)->get(route('leads.show', $lead))->assertOk();

        $this->actingAs($senior)->put(route('leads.update', $lead), [
            'company_name' => 'Updated by Senior Rep',
            'contact_person' => $lead->contact_person,
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'company_name' => 'Updated by Senior Rep']);
    }

    public function test_an_unrelated_peer_can_now_see_and_view_the_lead(): void
    {
        // Lead view/update is open to every user now (see LeadPolicy) —
        // hierarchy no longer restricts visibility, only who's "assigned".
        $senior = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $junior = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $senior->id]);
        $lead = Lead::factory()->create(['assigned_user_id' => $junior->id, 'created_by' => $junior->id]);

        $peer = User::factory()->create(['role' => UserRole::BusinessDevelopment]);

        $response = $this->actingAs($peer)->get(route('leads.index', ['created_by' => '']));
        $response->assertOk();
        $response->assertViewHas('leads', fn ($leads) => $leads->total() === 1);

        $this->actingAs($peer)->get(route('leads.show', $lead))->assertOk();
    }

    public function test_a_lead_created_by_a_report_but_assigned_elsewhere_is_still_visible(): void
    {
        $senior = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $junior = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $senior->id]);
        $otherRep = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $otherRep->id, 'created_by' => $junior->id]);

        $this->actingAs($senior)->get(route('leads.show', $lead))->assertOk();
    }
}
