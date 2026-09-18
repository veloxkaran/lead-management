<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementStatusChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_page_shows_the_status_change_modal_for_users_who_can_update(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $response->assertSee('Change Status');
        $response->assertSee('A note is required to change the status.');
    }

    public function test_status_can_be_changed_with_a_note(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'status' => RequirementStatus::Pending,
        ]);

        $response = $this->actingAs($user)->patch(route('requirements.status.update', $requirement), [
            'status' => RequirementStatus::InProgress->value,
            'note' => 'Started work on this today.',
        ]);

        $response->assertRedirect();
        $requirement->refresh();

        $this->assertSame(RequirementStatus::InProgress, $requirement->status);
        $this->assertDatabaseHas('requirement_comments', [
            'requirement_id' => $requirement->id,
            'author_id' => $user->id,
            'comment' => 'Status changed to In Progress: Started work on this today.',
        ]);
    }

    public function test_moving_to_completed_sets_completed_at_just_like_the_full_edit_form(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'status' => RequirementStatus::InProgress,
        ]);

        $this->actingAs($user)->patch(route('requirements.status.update', $requirement), [
            'status' => RequirementStatus::Completed->value,
            'note' => 'Delivered and verified.',
        ]);

        $requirement->refresh();

        $this->assertSame(RequirementStatus::Completed, $requirement->status);
        $this->assertNotNull($requirement->completed_at);
    }

    public function test_status_cannot_be_changed_without_a_note(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'status' => RequirementStatus::Pending,
        ]);

        $response = $this->actingAs($user)->patch(route('requirements.status.update', $requirement), [
            'status' => RequirementStatus::InProgress->value,
            'note' => '',
        ]);

        $response->assertSessionHasErrors('note');
        $this->assertSame(RequirementStatus::Pending, $requirement->fresh()->status);
        $this->assertDatabaseCount('requirement_comments', 0);
    }

    public function test_a_user_who_cannot_update_the_requirement_is_forbidden(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $owner->id,
            'status' => RequirementStatus::Pending,
        ]);

        $response = $this->actingAs($user)->patch(route('requirements.status.update', $requirement), [
            'status' => RequirementStatus::InProgress->value,
            'note' => 'Trying to sneak a change in.',
        ]);

        $response->assertForbidden();
        $this->assertSame(RequirementStatus::Pending, $requirement->fresh()->status);
    }
}
