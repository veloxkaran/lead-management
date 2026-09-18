<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketStatusLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_ticket_does_not_log_its_initial_status(): void
    {
        $raiser = User::factory()->create();

        $this->actingAs($raiser)->post(route('support-tickets.store'), [
            'subject' => 'New issue',
            'priority' => 'medium',
        ])->assertRedirect();

        $ticket = SupportTicket::firstWhere('subject', 'New issue');

        $this->assertCount(0, $ticket->statusLogs);
    }

    public function test_updating_status_via_edit_logs_the_transition(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $ticket = SupportTicket::factory()->create(['status' => RequirementStatus::Pending]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => RequirementStatus::InProgress->value,
        ])->assertRedirect();

        $ticket->refresh();

        $this->assertSame(RequirementStatus::InProgress, $ticket->status);

        $log = $ticket->statusLogs->first();
        $this->assertNotNull($log);
        $this->assertSame(RequirementStatus::Pending, $log->from_status);
        $this->assertSame(RequirementStatus::InProgress, $log->to_status);
        $this->assertSame($superAdmin->id, $log->changed_by);
    }

    public function test_resubmitting_the_same_status_is_a_no_op_and_creates_no_log(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $ticket = SupportTicket::factory()->create(['status' => RequirementStatus::Pending]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => RequirementStatus::Pending->value,
        ])->assertRedirect();

        $ticket->refresh();

        $this->assertCount(0, $ticket->statusLogs);
    }

    public function test_multiple_transitions_are_each_logged_in_order(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $ticket = SupportTicket::factory()->create(['status' => RequirementStatus::Pending]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => RequirementStatus::InProgress->value,
        ])->assertRedirect();

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => RequirementStatus::Completed->value,
        ])->assertRedirect();

        $ticket->refresh();

        $logs = $ticket->statusLogs()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(RequirementStatus::Pending, $logs[0]->from_status);
        $this->assertSame(RequirementStatus::InProgress, $logs[0]->to_status);
        $this->assertSame(RequirementStatus::InProgress, $logs[1]->from_status);
        $this->assertSame(RequirementStatus::Completed, $logs[1]->to_status);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_show_page_renders_status_history(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['name' => 'Jamie Admin']);
        $ticket = SupportTicket::factory()->create(['status' => RequirementStatus::Pending]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => RequirementStatus::InProgress->value,
        ])->assertRedirect();

        $response = $this->actingAs($superAdmin)->get(route('support-tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Status History');
        $response->assertSee('Jamie Admin');
    }

    public function test_show_page_reports_no_status_history_when_never_changed(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $response = $this->actingAs($user)->get(route('support-tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('No status changes yet.');
    }
}
