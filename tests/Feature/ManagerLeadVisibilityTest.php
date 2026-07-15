<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerLeadVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_leads_assigned_to_other_reps_in_the_index(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $repA = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $repB = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        Lead::factory()->create(['assigned_user_id' => $repA->id, 'created_by' => $repA->id]);
        Lead::factory()->create(['assigned_user_id' => $repB->id, 'created_by' => $repB->id]);

        $response = $this->actingAs($manager)->get(route('leads.index'));

        $response->assertOk();
        $response->assertViewHas('leads', fn ($leads) => $leads->total() === 2);
    }

    public function test_manager_can_view_and_update_a_lead_assigned_to_someone_else(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $rep = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $rep->id, 'created_by' => $rep->id]);

        $this->actingAs($manager)->get(route('leads.show', $lead))->assertOk();

        $this->actingAs($manager)->put(route('leads.update', $lead), [
            'company_name' => 'Updated by Manager',
            'contact_person' => $lead->contact_person,
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'company_name' => 'Updated by Manager']);
    }

    public function test_manager_can_view_their_own_assigned_lead_too(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $lead = Lead::factory()->create(['assigned_user_id' => $manager->id, 'created_by' => $manager->id]);

        $this->actingAs($manager)->get(route('leads.show', $lead))->assertOk();
    }
}
