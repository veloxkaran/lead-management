<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementClientAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_acknowledged_at_can_be_set_on_update_and_is_logged(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'client_acknowledged_at' => '2026-08-10T14:30',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertNotNull($requirement->client_acknowledged_at);
        $this->assertTrue($requirement->isAcknowledgedByClient());

        $entry = \App\Models\ActivityLogEntry::where('subject_type', Requirement::class)
            ->where('subject_id', $requirement->id)
            ->whereNotNull('new_values')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('client_acknowledged_at', $entry->new_values);
    }

    public function test_requirement_is_not_acknowledged_by_default(): void
    {
        $requirement = Requirement::factory()->create();

        $this->assertFalse($requirement->isAcknowledgedByClient());
    }

    public function test_lead_page_shows_acknowledgement_status(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'client_acknowledged_at' => '2026-08-10 14:30:00']);

        $response = $this->actingAs($user)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Client Acknowledged');
        $response->assertSee('Aug 10, 2026');
    }

    public function test_company_requirements_page_shows_acknowledgement_status(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'client_acknowledged_at' => null]);

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $response->assertSee('Client Acknowledged');
        $response->assertSee('Not yet');
    }

    public function test_pdf_export_includes_acknowledgement_column(): void
    {
        $user = User::factory()->create();
        Requirement::factory()->create(['client_acknowledged_at' => '2026-08-10 14:30:00']);

        $this->actingAs($user)->get(route('requirements.export-pdf'))->assertOk();
    }
}
