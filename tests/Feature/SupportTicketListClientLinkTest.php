<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketListClientLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_client_column_links_to_the_leads_detail_page(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp']);
        SupportTicket::factory()->create(['lead_id' => $lead->id]);

        $response = $this->actingAs($user)->get(route('support-tickets.index'));

        $response->assertOk();
        $response->assertSee(route('leads.show', $lead), false);
        $response->assertSeeInOrder(['Acme Corp'], false);
    }

    public function test_a_ticket_with_no_lead_shows_a_dash_instead_of_a_broken_link(): void
    {
        $user = User::factory()->create();
        SupportTicket::factory()->create(['lead_id' => null]);

        $response = $this->actingAs($user)->get(route('support-tickets.index'));

        $response->assertOk();
        $response->assertSee('&mdash;', false);
    }
}
