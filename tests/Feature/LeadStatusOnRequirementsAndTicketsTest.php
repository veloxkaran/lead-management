<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadStatusOnRequirementsAndTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_leads_status_shows_wherever_its_requirements_and_tickets_appear(): void
    {
        $status = LeadStatus::factory()->create(['name' => 'Proposal Sent', 'color' => '#f97316']);
        $lead = Lead::factory()->create(['company_name' => 'Acme Traders', 'lead_status_id' => $status->id]);
        $requirement = Requirement::factory()->create(['lead_id' => $lead->id]);
        $ticket = SupportTicket::factory()->create(['lead_id' => $lead->id]);

        $this->actingAs(User::factory()->superAdmin()->create());

        $pill = 'title="Lead status: Proposal Sent"';

        foreach ([
            route('support-tickets.index'),
            route('support-tickets.show', $ticket),
            route('requirements.index'),
            route('requirements.show', $requirement),
            route('requirements.company', $lead),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee($pill, false)->assertSee('--lead-status-color: #f97316', false);
        }
    }

    public function test_records_without_a_lead_show_no_pill(): void
    {
        SupportTicket::factory()->create(['lead_id' => null, 'subject' => 'Internal ticket']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('support-tickets.index'))
            ->assertSee('Internal ticket')
            ->assertDontSee('Lead status:', false);
    }
}
