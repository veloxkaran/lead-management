<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadOpenAccessAndAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_view_and_update_any_lead(): void
    {
        $owner = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $owner->id, 'created_by' => $owner->id]);

        $stranger = User::factory()->create(['role' => UserRole::Finance]);

        $this->actingAs($stranger)->get(route('leads.show', $lead))->assertOk();

        $this->actingAs($stranger)->put(route('leads.update', $lead), [
            'company_name' => 'Renamed by stranger',
            'contact_person' => $lead->contact_person,
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertSame('Renamed by stranger', $lead->fresh()->company_name);
    }

    public function test_updating_a_lead_logs_who_and_when_with_old_and_new_values(): void
    {
        $actor = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Original Co', 'contact_person' => 'Jane Doe']);

        $this->actingAs($actor)->put(route('leads.update', $lead), [
            'company_name' => 'Renamed Co',
            'contact_person' => 'Jane Doe',
        ])->assertRedirect();

        $entry = ActivityLogEntry::where('subject_type', Lead::class)
            ->where('subject_id', $lead->id)
            ->whereNotNull('new_values')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($actor->id, $entry->user_id);
        $this->assertSame('Original Co', $entry->old_values['company_name']);
        $this->assertSame('Renamed Co', $entry->new_values['company_name']);
        $this->assertNotNull($entry->created_at);
    }

    public function test_lead_page_shows_the_change_log_tab(): void
    {
        $actor = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Original Co', 'contact_person' => 'Jane Doe']);

        $this->actingAs($actor)->put(route('leads.update', $lead), [
            'company_name' => 'Renamed Co',
            'contact_person' => 'Jane Doe',
        ]);

        $response = $this->actingAs($actor)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Change Log');
        $response->assertSee($actor->name);
        $response->assertSee('Original Co');
        $response->assertSee('Renamed Co');
    }

    public function test_a_no_op_update_does_not_create_a_log_entry(): void
    {
        $actor = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Same Co', 'contact_person' => 'Jane Doe']);

        $this->actingAs($actor)->put(route('leads.update', $lead), [
            'company_name' => 'Same Co',
            'contact_person' => 'Jane Doe',
        ]);

        $this->assertSame(0, ActivityLogEntry::where('subject_type', Lead::class)
            ->where('subject_id', $lead->id)
            ->whereNotNull('new_values')
            ->count());
    }
}
