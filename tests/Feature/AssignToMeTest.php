<?php

namespace Tests\Feature;

use App\Enums\ActivityModule;
use App\Enums\RequirementStatus;
use App\Enums\SupportTicketAssignmentAction;
use App\Events\RequirementSaved;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\RequirementService;
use App\Services\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AssignToMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_details_page_offers_assign_to_me_on_an_unassigned_requirement(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create();

        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertOk()
            ->assertSee(route('requirements.assign-to-me', $requirement), false)
            ->assertSee('Assign to me');
    }

    public function test_any_user_can_assign_an_unassigned_requirement_to_themselves(): void
    {
        Event::fake([RequirementSaved::class]);
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['status' => RequirementStatus::Pending->value]);

        $this->actingAs($user)
            ->from(route('requirements.show', $requirement))
            ->post(route('requirements.assign-to-me', $requirement))
            ->assertRedirect(route('requirements.show', $requirement))
            ->assertSessionHas('success');

        $this->assertSame($user->id, $requirement->refresh()->assigned_to);
        $this->assertDatabaseHas('activity_log_entries', [
            'user_id' => $user->id,
            'module' => ActivityModule::Requirement->value,
            'subject_id' => $requirement->id,
        ]);
        Event::assertNotDispatched(RequirementSaved::class);

        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertDontSee(route('requirements.assign-to-me', $requirement), false);
    }

    public function test_an_already_assigned_requirement_cannot_be_taken(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['assigned_to' => $owner->id]);

        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertDontSee(route('requirements.assign-to-me', $requirement), false);
        $this->actingAs($user)->post(route('requirements.assign-to-me', $requirement))->assertForbidden();

        $this->assertSame($owner->id, $requirement->refresh()->assigned_to);
    }

    public function test_a_completed_requirement_cannot_be_claimed(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['status' => RequirementStatus::Completed->value]);

        $this->actingAs($user)->post(route('requirements.assign-to-me', $requirement))->assertForbidden();

        $this->assertNull($requirement->refresh()->assigned_to);
    }

    public function test_losing_a_simultaneous_requirement_claim_is_reported_not_overwritten(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $requirement = Requirement::factory()->create();

        // The loser's copy was loaded before the winner's claim landed.
        $stale = Requirement::find($requirement->id);
        $service = app(RequirementService::class);

        $this->assertTrue($service->assignToSelf($requirement, $winner, null, null));
        $this->assertFalse($service->assignToSelf($stale, $loser, null, null));

        $this->assertSame($winner->id, $requirement->refresh()->assigned_to);
    }

    public function test_requirement_claim_needs_edit_permission(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['permissions' => ['requirements' => ['view']]])->save();
        $requirement = Requirement::factory()->create();

        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertOk()
            ->assertDontSee('Assign to me');
        $this->actingAs($user)->post(route('requirements.assign-to-me', $requirement))->assertForbidden();

        $this->assertNull($requirement->refresh()->assigned_to);
    }

    public function test_any_user_can_assign_an_unassigned_ticket_to_themselves_and_it_is_logged(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->get(route('support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee(route('support-tickets.assign-to-me', $ticket), false);

        $this->actingAs($user)
            ->from(route('support-tickets.show', $ticket))
            ->post(route('support-tickets.assign-to-me', $ticket))
            ->assertRedirect(route('support-tickets.show', $ticket))
            ->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame($user->id, $ticket->assigned_to);
        $this->assertSame($user->id, $ticket->assigned_by);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertDatabaseHas('support_ticket_assignment_logs', [
            'support_ticket_id' => $ticket->id,
            'action' => SupportTicketAssignmentAction::Assigned->value,
            'user_id' => $user->id,
            'performed_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('support-tickets.show', $ticket))
            ->assertDontSee(route('support-tickets.assign-to-me', $ticket), false);
    }

    public function test_an_assigned_or_completed_ticket_cannot_be_claimed(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $assigned = SupportTicket::factory()->create(['assigned_to' => $owner->id]);
        $completed = SupportTicket::factory()->create(['status' => RequirementStatus::Completed->value]);

        $this->actingAs($user)->post(route('support-tickets.assign-to-me', $assigned))->assertForbidden();
        $this->actingAs($user)->post(route('support-tickets.assign-to-me', $completed))->assertForbidden();

        $this->assertSame($owner->id, $assigned->refresh()->assigned_to);
        $this->assertNull($completed->refresh()->assigned_to);
        $this->assertDatabaseCount('support_ticket_assignment_logs', 0);
    }

    public function test_losing_a_simultaneous_ticket_claim_writes_no_log(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $stale = SupportTicket::find($ticket->id);
        $service = app(SupportTicketService::class);

        $this->assertTrue($service->assignToSelf($ticket, $winner));
        $this->assertFalse($service->assignToSelf($stale, $loser));

        $this->assertSame($winner->id, $ticket->refresh()->assigned_to);
        $this->assertDatabaseCount('support_ticket_assignment_logs', 1);
    }
}
