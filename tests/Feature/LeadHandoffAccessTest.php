<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadHandoffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_success_can_view_a_lead_via_a_support_ticket_handoff(): void
    {
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $lead = Lead::factory()->create();
        SupportTicket::factory()->create(['lead_id' => $lead->id]);

        $this->actingAs($cs)->get(route('leads.show', $lead))->assertOk();
    }

    public function test_customer_success_can_view_a_lead_even_with_no_handoff(): void
    {
        // Lead view/update is open to every user now (see LeadPolicy::view()) —
        // the handoff mechanism only ever mattered when visibility was
        // restricted; this documents that a handoff is no longer required.
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $lead = Lead::factory()->create();

        $this->actingAs($cs)->get(route('leads.show', $lead))->assertOk();
    }

    public function test_finance_can_view_a_lead_even_with_no_handoff(): void
    {
        $finance = User::factory()->create(['role' => UserRole::Finance]);
        $lead = Lead::factory()->create();

        $this->actingAs($finance)->get(route('leads.show', $lead))->assertOk();
    }

    public function test_customer_success_cannot_change_status_or_close_a_lead(): void
    {
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $lead = Lead::factory()->create();

        $this->actingAs($cs)->post(route('leads.archive', $lead))->assertForbidden();
    }
}
