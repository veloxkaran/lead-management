<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementCompanyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_page_lists_only_that_companys_requirements(): void
    {
        $user = User::factory()->create();
        $acme = Lead::factory()->create(['company_name' => 'Acme Corp']);
        $globex = Lead::factory()->create(['company_name' => 'Globex Inc']);
        Requirement::factory()->create(['lead_id' => $acme->id, 'requirement' => 'Acme requirement']);
        Requirement::factory()->create(['lead_id' => $globex->id, 'requirement' => 'Globex requirement']);

        $response = $this->actingAs($user)->get(route('requirements.company', $acme));

        $response->assertOk();
        $response->assertSee('Acme requirement');
        $response->assertDontSee('Globex requirement');
    }

    public function test_company_page_includes_the_full_requirement_in_an_expandable_row(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        $longText = str_repeat('Detailed requirement text. ', 10).'THE-FULL-ENDING';
        Requirement::factory()->create(['lead_id' => $lead->id, 'title' => 'Short title', 'requirement' => "<p>{$longText}</p>"]);

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $response->assertSee('Short title');
        $response->assertSee('THE-FULL-ENDING');
        $response->assertSee('Expand all');
    }

    public function test_company_page_shows_created_date_and_solving_time_for_each_requirement(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create([
            'lead_id' => $lead->id,
            'status' => RequirementStatus::Completed,
            'created_at' => '2026-09-01 09:00:00',
            'completed_at' => '2026-09-02 11:30:00',
        ]);
        Requirement::factory()->create([
            'lead_id' => $lead->id,
            'status' => RequirementStatus::Pending,
            'created_at' => '2026-09-10 14:15:00',
        ]);

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $response->assertSee('Generated / Solved');
        $response->assertSee('Sep 01, 2026 9:00 AM');
        $response->assertSee('Sep 02, 2026 11:30 AM');
        $response->assertSee('Solved in 1 days, 2 hour and 30 min');
        $response->assertSee('Sep 10, 2026 2:15 PM');
        $response->assertSee("ticketElapsed('2026-09-10T14:15:00", false);
    }

    public function test_company_page_shows_summary_and_quick_filters(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Completed]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending, 'due_date' => now()->subDay()]);

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('summary')->total);
        $this->assertSame(1, $response->viewData('summary')->overdue);
        $response->assertSee('1/2 done');
        $response->assertSee('Search requirements or assignee');
        $response->assertSee("view = 'overdue'", false);
    }

    public function test_company_page_shows_the_company_wide_status_badge(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Completed]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending]);

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $response->assertSee('Partially Done');
    }

    public function test_company_page_has_a_link_back_to_the_company_list(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id]);

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $response->assertSee(route('requirements.index'), false);
    }

    public function test_company_page_shows_empty_state_when_the_company_has_no_requirements(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $response = $this->actingAs($user)->get(route('requirements.company', $lead));

        $response->assertOk();
        $response->assertSee('No requirements yet');
    }
}
