<?php

namespace Tests\Feature;

use App\Enums\SupportTicketAssignmentAction;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketAssignmentLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_at_creation_logs_who_assigned_it_and_sets_assigned_by_and_at(): void
    {
        $raiser = User::factory()->create();
        $assignee = User::factory()->create();

        $this->actingAs($raiser)->post(route('support-tickets.store'), [
            'subject' => 'Client cannot log in',
            'priority' => 'high',
            'assigned_to' => $assignee->id,
        ])->assertRedirect();

        $ticket = SupportTicket::firstWhere('subject', 'Client cannot log in');

        $this->assertSame($assignee->id, $ticket->assigned_to);
        $this->assertSame($raiser->id, $ticket->assigned_by);
        $this->assertNotNull($ticket->assigned_at);

        $log = $ticket->assignmentLogs->first();
        $this->assertNotNull($log);
        $this->assertSame(SupportTicketAssignmentAction::Assigned, $log->action);
        $this->assertSame($assignee->id, $log->user_id);
        $this->assertSame($raiser->id, $log->performed_by);
    }

    public function test_raising_unassigned_creates_no_assignment_log(): void
    {
        $raiser = User::factory()->create();

        $this->actingAs($raiser)->post(route('support-tickets.store'), [
            'subject' => 'Unassigned issue',
            'priority' => 'medium',
        ])->assertRedirect();

        $ticket = SupportTicket::firstWhere('subject', 'Unassigned issue');

        $this->assertNull($ticket->assigned_by);
        $this->assertNull($ticket->assigned_at);
        $this->assertCount(0, $ticket->assignmentLogs);
    }

    public function test_reassigning_via_edit_logs_unassigned_for_the_old_assignee_and_assigned_for_the_new_one(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $firstAssignee = User::factory()->create();
        $secondAssignee = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['assigned_to' => $firstAssignee->id]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
            'assigned_to' => $secondAssignee->id,
        ])->assertRedirect();

        $ticket->refresh();

        $this->assertSame($secondAssignee->id, $ticket->assigned_to);
        $this->assertSame($superAdmin->id, $ticket->assigned_by);

        $logs = $ticket->assignmentLogs()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(SupportTicketAssignmentAction::Unassigned, $logs[0]->action);
        $this->assertSame($firstAssignee->id, $logs[0]->user_id);
        $this->assertSame(SupportTicketAssignmentAction::Assigned, $logs[1]->action);
        $this->assertSame($secondAssignee->id, $logs[1]->user_id);
        $this->assertSame($superAdmin->id, $logs[0]->performed_by);
        $this->assertSame($superAdmin->id, $logs[1]->performed_by);
    }

    public function test_unassigning_via_edit_logs_unassigned_and_clears_assigned_by_and_at(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $assignee = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['assigned_to' => $assignee->id]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
            'assigned_to' => null,
        ])->assertRedirect();

        $ticket->refresh();

        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->assigned_by);
        $this->assertNull($ticket->assigned_at);

        $log = $ticket->assignmentLogs->first();
        $this->assertSame(SupportTicketAssignmentAction::Unassigned, $log->action);
        $this->assertSame($assignee->id, $log->user_id);
    }

    public function test_resubmitting_the_same_assignee_is_a_no_op_and_creates_no_log(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $assignee = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['assigned_to' => $assignee->id]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
            'assigned_to' => $assignee->id,
        ])->assertRedirect();

        $ticket->refresh();

        $this->assertSame($assignee->id, $ticket->assigned_to);
        $this->assertNull($ticket->assigned_by);
        $this->assertNull($ticket->assigned_at);
        $this->assertCount(0, $ticket->assignmentLogs);
    }

    public function test_show_page_renders_assignment_history(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $assignee = User::factory()->create(['name' => 'Jordan Assignee']);
        $ticket = SupportTicket::factory()->create();

        app(\App\Services\SupportTicketService::class)->assign($ticket, $assignee->id, $superAdmin);

        $response = $this->actingAs($superAdmin)->get(route('support-tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Assignment History');
        $response->assertSee('Jordan Assignee');
        $response->assertSee('Assigned');
    }

    public function test_show_page_reports_no_assignment_history_when_never_assigned(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $response = $this->actingAs($user)->get(route('support-tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('No assignment history yet.');
    }
}
