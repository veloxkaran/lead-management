<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementCompanyGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_groups_requirements_under_their_company(): void
    {
        $user = User::factory()->create();
        $acme = Lead::factory()->create(['company_name' => 'Acme Corp']);
        $globex = Lead::factory()->create(['company_name' => 'Globex Inc']);
        Requirement::factory()->create(['lead_id' => $acme->id, 'requirement' => 'Acme requirement one']);
        Requirement::factory()->create(['lead_id' => $acme->id, 'requirement' => 'Acme requirement two']);
        Requirement::factory()->create(['lead_id' => $globex->id, 'requirement' => 'Globex requirement']);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();

        $companies = $response->viewData('companies');
        $this->assertSame(['Acme Corp', 'Globex Inc'], $companies->pluck('company_name')->all());
        $this->assertCount(2, $companies->firstWhere('company_name', 'Acme Corp')->requirements);
        $this->assertCount(1, $companies->firstWhere('company_name', 'Globex Inc')->requirements);
    }

    public function test_leads_with_no_requirements_are_not_listed(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['company_name' => 'No Requirements Co']);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();
        $response->assertDontSee('No Requirements Co');
    }

    public function test_company_status_badge_reflects_all_of_that_companys_requirements_not_just_filtered_ones(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Mixed Status Co']);
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Completed]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending]);

        // Filtering to only the completed requirement should still show the
        // company's full, unfiltered list underneath it (per product intent:
        // filters decide which companies qualify, not which of their rows show).
        $response = $this->actingAs($user)->get(route('requirements.index', ['status' => 'completed']));

        $response->assertOk();

        $companies = $response->viewData('companies');
        $this->assertCount(1, $companies);
        $this->assertCount(2, $companies->first()->requirements);
        $response->assertSee('Partially Done');
    }

    public function test_a_company_with_all_requirements_done_shows_a_done_badge(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'All Done Co']);
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Completed]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Completed]);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();
        $response->assertSee('Done');
    }

    public function test_pagination_is_by_company_not_by_requirement_row(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->count(25)->create(['lead_id' => $lead->id]);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();

        $companies = $response->viewData('companies');
        $this->assertCount(1, $companies);
        $this->assertFalse($companies->hasPages());
        $this->assertCount(25, $companies->first()->requirements);
    }
}
