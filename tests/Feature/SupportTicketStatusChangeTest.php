<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Support Tickets list's quick "Change status" popup — status plus a
 * required note, like the Requirements list's.
 */
class SupportTicketStatusChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_the_status_popup(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->get(route('support-tickets.index'))
            ->assertOk()
            ->assertSee('ticketStatusModal-'.$ticket->id)
            ->assertSee(route('support-tickets.status.update', $ticket), false);
    }

    public function test_status_can_be_changed_with_a_note(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->patch(route('support-tickets.status.update', $ticket), [
            'status' => 'in_progress',
            'note' => 'Looking into it now.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(RequirementStatus::InProgress, $ticket->status);
        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'author_id' => $user->id,
            'comment' => 'Status changed to In Progress: Looking into it now.',
        ]);
        $this->assertDatabaseHas('support_ticket_status_logs', [
            'support_ticket_id' => $ticket->id,
            'from_status' => 'pending',
            'to_status' => 'in_progress',
            'changed_by' => $user->id,
        ]);
    }

    public function test_a_note_is_required(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->patch(route('support-tickets.status.update', $ticket), ['status' => 'completed', 'note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame(RequirementStatus::Pending, $ticket->fresh()->status);
    }

    public function test_completing_sets_resolved_at(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->patch(route('support-tickets.status.update', $ticket), ['status' => 'completed', 'note' => 'Fixed in v2.1']);

        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_choosing_cancelled_makes_the_note_the_reason_with_one_comment(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->patch(route('support-tickets.status.update', $ticket), [
            'status' => 'cancelled',
            'note' => 'Raised by mistake',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(RequirementStatus::Cancelled, $ticket->status);
        $this->assertSame('Raised by mistake', $ticket->cancellation_reason);
        $this->assertSame($user->id, $ticket->cancelled_by);
        $this->assertSame(['Cancelled: Raised by mistake'], $ticket->comments()->pluck('comment')->all());
    }

    public function test_a_locked_ticket_cannot_be_changed_from_the_popup(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create([
            'status' => RequirementStatus::Completed,
            'resolved_at' => now()->subHours(SupportTicket::EDIT_WINDOW_HOURS + 1),
        ]);

        $this->actingAs($user)->patch(route('support-tickets.status.update', $ticket), ['status' => 'pending', 'note' => 'x'])
            ->assertForbidden();
    }

    public function test_a_failed_popup_reopens_itself_not_the_raise_ticket_modal(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->from(route('support-tickets.index'))
            ->patch(route('support-tickets.status.update', $ticket), ['status' => 'completed', 'note' => '', '_form' => 'ticket_status_'.$ticket->id]);

        $this->actingAs($user)->get(route('support-tickets.index'))
            ->assertDontSee("getElementById('raiseTicketModal')).show()", false)
            ->assertSee('ticketStatusModal-'.$ticket->id.'"', false)
            ->assertSee('Add a note explaining the status change.');
    }
}
