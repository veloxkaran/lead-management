<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketRaiseModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_offers_a_raise_ticket_modal_with_the_lead_dropdown(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp']);

        $response = $this->actingAs($user)->get(route('support-tickets.index'));

        $response->assertOk();
        $response->assertSee('id="raiseTicketModal"', false);
        $response->assertSee('Acme Corp');
        $response->assertSee(route('support-tickets.store'));
    }

    public function test_submitting_the_modal_form_raises_a_ticket(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $response = $this->actingAs($user)->post(route('support-tickets.store'), [
            'lead_id' => $lead->id,
            'subject' => 'Raised from the modal',
            'priority' => 'medium',
        ]);

        $response->assertRedirect(route('support-tickets.index'));
        $this->assertDatabaseHas('support_tickets', [
            'lead_id' => $lead->id,
            'subject' => 'Raised from the modal',
        ]);
    }

    public function test_a_validation_failure_reopens_the_modal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('support-tickets.index'))->post(route('support-tickets.store'), [
            'priority' => 'medium',
            // subject is required and missing.
        ]);

        $response->assertRedirect(route('support-tickets.index'));
        $response->assertSessionHasErrors('subject');

        $follow = $this->get(route('support-tickets.index'));
        $follow->assertSee('new bootstrap.Modal(document.getElementById(\'raiseTicketModal\')).show();', false);
    }
}
