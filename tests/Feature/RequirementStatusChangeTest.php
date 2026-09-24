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

    public function test_company_page_shows_the_status_change_modal_to_any_user(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'created_by' => $owner->id]);

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

    /**
     * Reopening a completed requirement must clear completed_at — otherwise
     * it keeps counting as "closed" (and skewing the average closing time)
     * on the dashboard's Performance Snapshot even though it's active again.
     */
    public function test_reopening_a_completed_requirement_clears_completed_at(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'status' => RequirementStatus::Completed,
            // Inside the 4-hour edit window — after it only Super Admin can
            // reopen (see CompletionLockTest).
            'completed_at' => now()->subHours(2),
        ]);

        $this->actingAs($user)->patch(route('requirements.status.update', $requirement), [
            'status' => RequirementStatus::InProgress->value,
            'note' => 'Client found a regression, reopening.',
        ]);

        $requirement->refresh();

        $this->assertSame(RequirementStatus::InProgress, $requirement->status);
        $this->assertNull($requirement->completed_at);
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

    public function test_any_user_can_change_status_even_without_update_permission_on_the_requirement(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $owner->id,
            'assigned_to' => null,
            'status' => RequirementStatus::Pending,
        ]);

        $this->assertFalse($user->can('update', $requirement));

        $response = $this->actingAs($user)->patch(route('requirements.status.update', $requirement), [
            'status' => RequirementStatus::InProgress->value,
            'note' => 'Picking this up.',
        ]);

        $response->assertRedirect();
        $this->assertSame(RequirementStatus::InProgress, $requirement->fresh()->status);
        $this->assertDatabaseHas('requirement_comments', [
            'requirement_id' => $requirement->id,
            'author_id' => $user->id,
            'comment' => 'Status changed to In Progress: Picking this up.',
        ]);
    }

    public function test_edit_link_still_requires_update_permission_while_status_change_does_not(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $owner->id,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $response->assertSee('statusModal-'.$requirement->id);
        $response->assertDontSee(route('requirements.edit', $requirement));
    }
}
