<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementDueDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_date_can_be_set_when_creating_a_requirement_from_the_lead_page(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->from(route('leads.show', $lead))->post(route('leads.requirements.store', $lead), [
            'requirement' => 'Needs SSO integration',
            'priority' => 'high',
            'due_date' => '2026-08-15',
        ])->assertRedirect();

        $requirement = Requirement::firstWhere('requirement', 'Needs SSO integration');
        $this->assertNotNull($requirement);
        $this->assertSame('2026-08-15', $requirement->due_date->toDateString());
    }

    public function test_updating_the_due_date_is_logged_with_old_and_new_value(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create([
            'created_by' => $user->id,
            'due_date' => '2026-08-01',
        ]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'due_date' => '2026-09-01',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertSame('2026-09-01', $requirement->due_date->toDateString());

        $this->assertDatabaseHas('activity_log_entries', [
            'subject_type' => Requirement::class,
            'subject_id' => $requirement->id,
            'user_id' => $user->id,
        ]);

        $entry = \App\Models\ActivityLogEntry::where('subject_type', Requirement::class)
            ->where('subject_id', $requirement->id)
            ->whereNotNull('new_values')
            ->latest('id')
            ->first();
        $this->assertNotNull($entry);
        $this->assertStringStartsWith('2026-08-01', $entry->old_values['due_date']);
        $this->assertStringStartsWith('2026-09-01', $entry->new_values['due_date']);
    }

    public function test_change_log_is_visible_on_the_requirement_edit_page(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create([
            'created_by' => $user->id,
            'due_date' => '2026-08-01',
        ]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'due_date' => '2026-09-01',
        ]);

        $response = $this->actingAs($user)->get(route('requirements.edit', $requirement));

        $response->assertOk();
        $response->assertSee('Change Log');
        $response->assertSee('Due Date');
        $response->assertSee('2026-08-01');
        $response->assertSee('2026-09-01');
    }

    public function test_lead_page_shows_due_date_column_for_requirements(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'due_date' => '2026-08-20']);

        $response = $this->actingAs($user)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee('Due Date');
        $response->assertSee('Aug 20, 2026');
    }
}
