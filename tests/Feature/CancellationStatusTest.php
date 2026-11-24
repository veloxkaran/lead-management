<?php

namespace Tests\Feature;

use App\Enums\CompanyRequirementStatus;
use App\Enums\RequirementStatus;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\RequirementSummary;
use App\Support\SupportTicketSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requirements and support tickets can be Cancelled, always with a reason.
 */
class CancellationStatusTest extends TestCase
{
    use RefreshDatabase;

    // ---- Support tickets ----

    public function test_a_ticket_can_be_cancelled_with_a_reason(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['raised_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('support-tickets.cancel', $ticket), ['cancellation_reason' => '  Client withdrew the request.  '])
            ->assertRedirect(route('support-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame(RequirementStatus::Cancelled, $ticket->status);
        $this->assertSame('Client withdrew the request.', $ticket->cancellation_reason);
        $this->assertSame($user->id, $ticket->cancelled_by);
        $this->assertNotNull($ticket->cancelled_at);
        $this->assertNull($ticket->resolved_at, 'Cancelled is not solved.');
        $this->assertFalse($ticket->isOverdue());

        $this->assertDatabaseHas('support_ticket_status_logs', [
            'support_ticket_id' => $ticket->id,
            'from_status' => 'pending',
            'to_status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'comment' => 'Cancelled: Client withdrew the request.',
        ]);
    }

    public function test_cancelling_a_ticket_requires_a_reason(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)
            ->post(route('support-tickets.cancel', $ticket), ['cancellation_reason' => ''])
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame(RequirementStatus::Pending, $ticket->fresh()->status);
    }

    public function test_the_ticket_edit_form_requires_a_reason_when_choosing_cancelled(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $payload = [
            'subject' => $ticket->subject,
            'priority' => $ticket->priority->value,
            'status' => 'cancelled',
        ];

        $this->actingAs($user)->put(route('support-tickets.update', $ticket), $payload)
            ->assertSessionHasErrors('cancellation_reason');

        $this->actingAs($user)->put(route('support-tickets.update', $ticket), $payload + ['cancellation_reason' => 'Duplicate of #12'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(RequirementStatus::Cancelled, $ticket->status);
        $this->assertSame('Duplicate of #12', $ticket->cancellation_reason);
        $this->assertSame($user->id, $ticket->cancelled_by);
    }

    public function test_reopening_a_cancelled_ticket_clears_the_cancellation(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['raised_by' => $user->id]);

        $this->actingAs($user)->post(route('support-tickets.cancel', $ticket), ['cancellation_reason' => 'Not needed']);
        $this->actingAs($user)->post(route('support-tickets.reopen', $ticket), ['reason' => 'Needed after all'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(RequirementStatus::Pending, $ticket->status);
        $this->assertNull($ticket->cancellation_reason);
        $this->assertNull($ticket->cancelled_at);
        $this->assertNull($ticket->cancelled_by);
    }

    public function test_a_cancelled_ticket_cannot_be_cancelled_again_or_claimed(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $this->actingAs($user)->post(route('support-tickets.cancel', $ticket), ['cancellation_reason' => 'Spam']);

        $this->actingAs($user)->post(route('support-tickets.cancel', $ticket), ['cancellation_reason' => 'Again'])
            ->assertForbidden();
        $this->actingAs($user)->post(route('support-tickets.assign-to-me', $ticket))
            ->assertForbidden();
    }

    public function test_ticket_show_page_shows_the_reason_and_list_counts_it_separately(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['subject' => 'Cancelled one']);
        SupportTicket::factory()->create(['subject' => 'Still open']);
        $this->actingAs($user)->post(route('support-tickets.cancel', $ticket), ['cancellation_reason' => 'Client went with another vendor']);

        $this->actingAs($user)->get(route('support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('This support ticket was cancelled')
            ->assertSee('Client went with another vendor')
            ->assertDontSee('Cancel Ticket');

        $this->actingAs($user)->get(route('support-tickets.index', ['view' => 'open']))
            ->assertSee('Still open')
            ->assertDontSee('Cancelled one');

        $this->actingAs($user)->get(route('support-tickets.index', ['view' => 'cancelled']))
            ->assertSee('Cancelled one')
            ->assertDontSee('Still open');

        $summary = SupportTicketSummary::of(SupportTicket::all());
        $this->assertSame(1, $summary->open);
        $this->assertSame(1, $summary->cancelled);
    }

    // ---- Requirements ----

    public function test_a_requirement_can_be_cancelled_with_a_reason(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id, 'status' => RequirementStatus::Pending]);

        $this->actingAs($user)
            ->post(route('requirements.cancel', $requirement), ['cancellation_reason' => 'Out of scope'])
            ->assertRedirect(route('requirements.show', $requirement));

        $requirement->refresh();
        $this->assertSame(RequirementStatus::Cancelled, $requirement->status);
        $this->assertSame('Out of scope', $requirement->cancellation_reason);
        $this->assertSame($user->id, $requirement->cancelled_by);
        $this->assertNotNull($requirement->cancelled_at);
        $this->assertNull($requirement->completed_at, 'Cancelled is not solved.');

        $this->assertDatabaseHas('requirement_comments', [
            'requirement_id' => $requirement->id,
            'comment' => 'Cancelled: Out of scope',
        ]);

        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertOk()
            ->assertSee('This requirement was cancelled')
            ->assertSee('Out of scope');
    }

    public function test_the_quick_status_popup_uses_the_note_as_the_reason_with_one_comment(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id, 'status' => RequirementStatus::InProgress]);

        $this->actingAs($user)->patch(route('requirements.status.update', $requirement), [
            'status' => 'cancelled',
            'note' => 'Client dropped this feature',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertSame(RequirementStatus::Cancelled, $requirement->status);
        $this->assertSame('Client dropped this feature', $requirement->cancellation_reason);
        $this->assertSame(1, $requirement->comments()->count());
        $this->assertSame('Cancelled: Client dropped this feature', $requirement->comments()->value('comment'));
    }

    public function test_the_requirement_edit_form_requires_a_reason_and_reopening_clears_it(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id, 'status' => RequirementStatus::Pending]);
        $payload = [
            'system_module_id' => $requirement->system_module_id,
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => 'cancelled',
        ];

        $this->actingAs($user)->put(route('requirements.update', $requirement), $payload)
            ->assertSessionHasErrors('cancellation_reason');

        $this->actingAs($user)->put(route('requirements.update', $requirement), $payload + ['cancellation_reason' => 'Merged into another'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Merged into another', $requirement->fresh()->cancellation_reason);

        $this->actingAs($user)->put(route('requirements.update', $requirement), ['status' => 'pending', 'cancellation_reason' => 'ignored'] + $payload)
            ->assertSessionHasNoErrors();

        $requirement->refresh();
        $this->assertSame(RequirementStatus::Pending, $requirement->status);
        $this->assertNull($requirement->cancellation_reason);
        $this->assertNull($requirement->cancelled_at);
    }

    public function test_cancelled_requirements_are_closed_and_left_out_of_progress(): void
    {
        $requirements = collect([
            Requirement::factory()->make(['status' => RequirementStatus::Completed]),
            Requirement::factory()->make(['status' => RequirementStatus::Cancelled, 'due_date' => now()->subWeek()]),
        ]);

        $this->assertFalse($requirements[1]->isOverdue());

        $summary = RequirementSummary::of($requirements);
        $this->assertSame(0, $summary->open);
        $this->assertSame(1, $summary->cancelled);
        $this->assertSame(100, $summary->completionPercent());

        $this->assertSame(CompanyRequirementStatus::Done, CompanyRequirementStatus::fromRequirements($requirements));
        $this->assertSame(CompanyRequirementStatus::Done, CompanyRequirementStatus::fromRequirements([$requirements[1]]));
        $this->assertSame(CompanyRequirementStatus::Pending, CompanyRequirementStatus::fromRequirements([
            $requirements[1], Requirement::factory()->make(['status' => RequirementStatus::Pending]),
        ]));
    }

    public function test_a_cancelled_record_locks_after_the_edit_window(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);
        $this->actingAs($user)->post(route('requirements.cancel', $requirement), ['cancellation_reason' => 'No longer needed']);

        $requirement->refresh()->forceFill(['cancelled_at' => now()->subHours(Requirement::EDIT_WINDOW_HOURS + 1)])->saveQuietly();

        $this->assertTrue($requirement->fresh()->isLocked());
        $this->actingAs($user)->get(route('requirements.edit', $requirement))->assertForbidden();
    }
}
