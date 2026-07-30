<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketCompanySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_can_be_filtered_by_company_name(): void
    {
        $user = User::factory()->create();
        $acme = Lead::factory()->create(['company_name' => 'Acme Corp']);
        $globex = Lead::factory()->create(['company_name' => 'Globex Inc']);
        SupportTicket::factory()->create(['lead_id' => $acme->id, 'subject' => 'Acme cannot log in']);
        SupportTicket::factory()->create(['lead_id' => $globex->id, 'subject' => 'Globex billing issue']);

        $response = $this->actingAs($user)->get(route('support-tickets.index', ['search' => 'Acme']));

        $response->assertOk();
        $response->assertSee('Acme cannot log in');
        $response->assertDontSee('Globex billing issue');
    }

    public function test_company_search_is_case_insensitive_and_partial(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp']);
        SupportTicket::factory()->create(['lead_id' => $lead->id, 'subject' => 'Findable ticket']);

        $response = $this->actingAs($user)->get(route('support-tickets.index', ['search' => 'acme']));

        $response->assertOk();
        $response->assertSee('Findable ticket');
    }
}
