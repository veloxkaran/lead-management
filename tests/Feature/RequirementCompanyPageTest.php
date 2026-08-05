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
