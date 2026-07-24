<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AccountRequest;
use App\Models\ImplementationRequest;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_development_can_raise_an_implementation_request(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $bde->id, 'created_by' => $bde->id]);

        $this->actingAs($bde)->post(route('implementation-requests.store'), [
            'lead_id' => $lead->id,
            'title' => 'Onboard Acme Corp',
            'details' => 'Kickoff scheduled for next week.',
        ])->assertRedirect(route('implementation-requests.index'));

        $this->assertDatabaseHas('implementation_requests', [
            'lead_id' => $lead->id,
            'title' => 'Onboard Acme Corp',
            'requested_by' => $bde->id,
            'status' => 'not_started',
        ]);
    }

    public function test_finance_cannot_view_implementation_requests(): void
    {
        $finance = User::factory()->create(['role' => UserRole::Finance]);

        $this->actingAs($finance)->get(route('implementation-requests.index'))->assertForbidden();
    }

    public function test_customer_success_can_view_and_update_an_implementation_request(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $lead = Lead::factory()->create();
        $request = ImplementationRequest::factory()->create([
            'lead_id' => $lead->id,
            'requested_by' => $bde->id,
            'title' => 'Onboard Acme Corp',
        ]);

        $this->actingAs($cs)->get(route('implementation-requests.index'))->assertOk();

        $this->actingAs($cs)->put(route('implementation-requests.update', $request), [
            'title' => $request->title,
            'status' => 'completed',
        ])->assertRedirect(route('implementation-requests.index'));

        $this->assertDatabaseHas('implementation_requests', ['id' => $request->id, 'status' => 'completed']);
        $this->assertNotNull($request->fresh()->completed_at);
    }

    public function test_business_development_can_raise_a_support_ticket(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);

        $this->actingAs($bde)->get(route('support-tickets.create'))->assertOk();
    }

    public function test_manager_can_raise_a_support_ticket_customer_success_sees_it(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);

        $this->actingAs($manager)->post(route('support-tickets.store'), [
            'subject' => 'Client cannot log in',
            'priority' => 'high',
        ])->assertRedirect(route('support-tickets.index'));

        $ticket = SupportTicket::firstWhere('subject', 'Client cannot log in');

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'raised_by' => $manager->id]);
        $this->actingAs($cs)->get(route('support-tickets.index'))->assertOk();
    }

    public function test_business_development_can_send_an_account_request_but_only_sees_their_own(): void
    {
        $bdeA = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $bdeB = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $bdeA->id, 'created_by' => $bdeA->id]);

        $this->actingAs($bdeA)->post(route('account-requests.store'), [
            'lead_id' => $lead->id,
            'request_type' => 'invoice',
            'amount' => 1500,
        ])->assertRedirect(route('account-requests.index'));

        AccountRequest::factory()->create(['requested_by' => $bdeB->id]);

        $response = $this->actingAs($bdeA)->get(route('account-requests.index'));
        $response->assertOk();
        $response->assertViewHas('requests', fn ($requests) => $requests->total() === 1);
    }

    public function test_finance_can_process_an_account_request(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $finance = User::factory()->create(['role' => UserRole::Finance]);
        $request = AccountRequest::factory()->create(['requested_by' => $bde->id, 'amount' => 2000]);

        $this->actingAs($finance)->put(route('account-requests.update', $request), [
            'request_type' => $request->request_type->value,
            'amount' => $request->amount,
            'status' => 'completed',
            'processed_by' => $finance->id,
        ])->assertRedirect(route('account-requests.index'));

        $this->assertDatabaseHas('account_requests', ['id' => $request->id, 'status' => 'completed', 'processed_by' => $finance->id]);
        $this->assertNotNull($request->fresh()->processed_at);
    }
}
