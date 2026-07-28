<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketAssignOnCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_assignee_can_be_set_while_raising_a_ticket(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();

        $this->actingAs($user)->post(route('support-tickets.store'), [
            'subject' => 'Client cannot log in',
            'priority' => 'high',
            'assigned_to' => $assignee->id,
        ])->assertRedirect();

        $ticket = SupportTicket::firstWhere('subject', 'Client cannot log in');

        $this->assertNotNull($ticket);
        $this->assertSame($assignee->id, $ticket->assigned_to);
    }

    public function test_a_ticket_can_still_be_raised_unassigned(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('support-tickets.store'), [
            'subject' => 'Unassigned issue',
            'priority' => 'medium',
        ])->assertRedirect();

        $ticket = SupportTicket::firstWhere('subject', 'Unassigned issue');

        $this->assertNotNull($ticket);
        $this->assertNull($ticket->assigned_to);
    }

    public function test_the_create_form_offers_an_assignee_select(): void
    {
        $user = User::factory()->create(['name' => 'Priya Assignee']);

        $response = $this->actingAs($user)->get(route('support-tickets.create'));

        $response->assertOk();
        $response->assertSee('Assign To');
        $response->assertSee('Priya Assignee');
    }

    public function test_an_assignee_can_be_set_while_raising_a_ticket_from_the_lead_page(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('leads.support-tickets.store', $lead), [
            'subject' => 'Onboarding blocker',
            'priority' => 'medium',
            'assigned_to' => $assignee->id,
        ])->assertRedirect();

        $ticket = SupportTicket::firstWhere('subject', 'Onboarding blocker');

        $this->assertNotNull($ticket);
        $this->assertSame($assignee->id, $ticket->assigned_to);
    }
}
