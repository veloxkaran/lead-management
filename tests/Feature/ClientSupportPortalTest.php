<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSupportPortalTest extends TestCase
{
    use RefreshDatabase;

    private function issueSupportAccess(Lead $lead): string
    {
        $admin = User::factory()->superAdmin()->create();

        return app(LeadService::class)->generateSupportAccess($lead, $admin);
    }

    public function test_correct_support_id_and_pin_verifies_and_allows_a_ticket_to_be_raised(): void
    {
        $lead = Lead::factory()->create();
        $pin = $this->issueSupportAccess($lead);
        $lead->refresh();

        $verify = $this->post(route('client-support.verify'), [
            'support_id' => $lead->support_id,
            'pin' => $pin,
        ]);
        $verify->assertRedirect(route('client-support.ticket.create'));

        $store = $this->post(route('client-support.ticket.store'), [
            'subject' => 'Cannot access the reporting dashboard',
            'details' => 'Getting a 500 error since this morning.',
        ]);
        $store->assertRedirect(route('client-support.ticket.submitted'));

        $lead->refresh();
        $this->assertCount(1, $lead->supportTickets);
        $ticket = $lead->supportTickets->first();
        $this->assertTrue($ticket->is_client_submitted);
        $this->assertSame('Cannot access the reporting dashboard', $ticket->subject);
        $this->assertSame($lead->assigned_user_id ?? $lead->created_by, $ticket->raised_by);
        $this->assertSame('Client (self-service portal)', $ticket->raiserDisplayName());
    }

    public function test_wrong_pin_is_rejected_with_a_generic_message(): void
    {
        $lead = Lead::factory()->create();
        $this->issueSupportAccess($lead);
        $lead->refresh();

        $response = $this->post(route('client-support.verify'), [
            'support_id' => $lead->support_id,
            'pin' => '0000',
        ]);

        $response->assertSessionHasErrors('pin');
        $this->assertFalse(session()->has('client_support_lead_id'));
    }

    public function test_unknown_support_id_is_rejected_with_the_same_generic_message(): void
    {
        $response = $this->post(route('client-support.verify'), [
            'support_id' => 'SPT-000000',
            'pin' => '1234',
        ]);

        $response->assertSessionHasErrors('pin');
        $this->assertSame(
            'That Support ID or PIN is incorrect.',
            session('errors')->first('pin')
        );
    }

    public function test_repeated_wrong_attempts_are_rate_limited_even_with_the_right_pin_afterward(): void
    {
        $lead = Lead::factory()->create();
        $pin = $this->issueSupportAccess($lead);
        $lead->refresh();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('client-support.verify'), [
                'support_id' => $lead->support_id,
                'pin' => '9999',
            ]);
        }

        // 6th attempt, this time with the correct PIN — still locked out.
        $response = $this->post(route('client-support.verify'), [
            'support_id' => $lead->support_id,
            'pin' => $pin,
        ]);

        $response->assertSessionHasErrors('pin');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('pin'));
        $this->assertFalse(session()->has('client_support_lead_id'));
    }

    public function test_ticket_form_requires_a_verified_session(): void
    {
        $response = $this->get(route('client-support.ticket.create'));

        $response->assertRedirect(route('client-support.show'));
    }

    public function test_ticket_store_requires_a_verified_session(): void
    {
        $response = $this->post(route('client-support.ticket.store'), [
            'subject' => 'Should not be accepted',
        ]);

        $response->assertRedirect(route('client-support.show'));
        $this->assertDatabaseMissing('support_tickets', ['subject' => 'Should not be accepted']);
    }
}
