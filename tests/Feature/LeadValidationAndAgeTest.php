<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadValidationAndAgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_near_duplicate_company_name_is_rejected_on_create(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['company_name' => 'Acme Corporation']);

        $response = $this->actingAs($user)->post(route('leads.store'), [
            'company_name' => 'Acme Corporations', // near-identical, one char added
            'contact_person' => 'Jane Doe',
        ]);

        $response->assertSessionHasErrors('company_name');
        $this->assertDatabaseMissing('leads', ['company_name' => 'Acme Corporations']);
    }

    public function test_a_sufficiently_different_company_name_is_accepted(): void
    {
        $user = User::factory()->create();
        $status = LeadStatus::factory()->create(['is_default' => true]);
        Lead::factory()->create(['company_name' => 'Acme Corporation']);

        $response = $this->actingAs($user)->post(route('leads.store'), [
            'company_name' => 'Globex Industries',
            'contact_person' => 'Jane Doe',
            'lead_status_id' => $status->id,
        ]);

        $response->assertSessionDoesntHaveErrors('company_name');
        $this->assertDatabaseHas('leads', ['company_name' => 'Globex Industries']);
    }

    public function test_updating_a_lead_does_not_flag_its_own_unchanged_name_as_a_duplicate(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'company_name' => 'Acme Corporation',
            'created_by' => $user->id,
            'assigned_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->put(route('leads.update', $lead), [
            'company_name' => 'Acme Corporation',
            'contact_person' => $lead->contact_person,
        ]);

        $response->assertSessionDoesntHaveErrors('company_name');
    }

    public function test_lead_reports_time_spent_in_its_current_status(): void
    {
        $user = User::factory()->create();
        $statusA = LeadStatus::factory()->create();
        $statusB = LeadStatus::factory()->create();
        $lead = Lead::factory()->create([
            'lead_status_id' => $statusA->id,
            'created_by' => $user->id,
            'assigned_user_id' => $user->id,
        ]);

        // No history yet — age falls back to created_at.
        $this->assertEquals($lead->created_at->timestamp, $lead->currentStatusSince()->timestamp);

        $this->travel(3)->days();

        $this->actingAs($user)->post(route('leads.status.update', $lead), [
            'lead_status_id' => $statusB->id,
        ]);

        $lead->refresh();

        $this->assertEqualsWithDelta(now()->timestamp, $lead->currentStatusSince()->timestamp, 5);
        $this->assertStringContainsString('second', $lead->currentStatusAge());
    }
}
