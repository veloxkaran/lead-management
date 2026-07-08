<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_leads_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('leads.index'))->assertOk();
    }

    public function test_user_can_create_a_lead(): void
    {
        $user = User::factory()->create();
        $status = LeadStatus::factory()->create(['is_default' => true]);

        $response = $this->actingAs($user)->post(route('leads.store'), [
            'company_name' => 'Acme Corp',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'lead_status_id' => $status->id,
        ]);

        $this->assertDatabaseHas('leads', ['company_name' => 'Acme Corp', 'created_by' => $user->id]);

        $lead = Lead::firstWhere('company_name', 'Acme Corp');
        $response->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('lead_status_histories', [
            'lead_id' => $lead->id,
            'to_status_id' => $status->id,
            'from_status_id' => null,
        ]);
    }

    public function test_lead_status_change_is_recorded_in_history(): void
    {
        $user = User::factory()->create();
        $statusA = LeadStatus::factory()->create();
        $statusB = LeadStatus::factory()->create();
        $lead = Lead::factory()->create(['lead_status_id' => $statusA->id, 'created_by' => $user->id, 'assigned_user_id' => $user->id]);

        $this->actingAs($user)->post(route('leads.status.update', $lead), [
            'lead_status_id' => $statusB->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'lead_status_id' => $statusB->id]);
        $this->assertDatabaseHas('lead_status_histories', [
            'lead_id' => $lead->id,
            'from_status_id' => $statusA->id,
            'to_status_id' => $statusB->id,
        ]);
    }

    public function test_user_cannot_delete_lead_unless_super_admin(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['created_by' => $user->id, 'assigned_user_id' => $user->id]);

        $this->actingAs($user)->delete(route('leads.destroy', $lead))->assertForbidden();
    }

    public function test_super_admin_can_delete_lead(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($admin)->delete(route('leads.destroy', $lead))->assertRedirect(route('leads.index'));

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_archiving_a_lead_hides_it_from_default_index_filter(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['created_by' => $user->id, 'assigned_user_id' => $user->id]);

        $this->actingAs($user)->post(route('leads.archive', $lead))->assertRedirect();

        $this->assertNotNull($lead->fresh()->archived_at);
    }
}
