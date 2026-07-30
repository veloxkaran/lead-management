<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementCompanySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_can_be_filtered_by_company_name(): void
    {
        $user = User::factory()->create();
        $acme = Lead::factory()->create(['company_name' => 'Acme Corp']);
        $globex = Lead::factory()->create(['company_name' => 'Globex Inc']);
        Requirement::factory()->create(['lead_id' => $acme->id, 'requirement' => 'Acme requirement']);
        Requirement::factory()->create(['lead_id' => $globex->id, 'requirement' => 'Globex requirement']);

        $response = $this->actingAs($user)->get(route('requirements.index', ['search' => 'Acme']));

        $response->assertOk();
        $response->assertSee('Acme requirement');
        $response->assertDontSee('Globex requirement');
    }

    public function test_company_search_is_case_insensitive_and_partial(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp']);
        Requirement::factory()->create(['lead_id' => $lead->id, 'requirement' => 'Findable requirement']);

        $response = $this->actingAs($user)->get(route('requirements.index', ['search' => 'acme']));

        $response->assertOk();
        $response->assertSee('Findable requirement');
    }

    public function test_pdf_export_respects_the_company_search_filter(): void
    {
        $user = User::factory()->create();
        $acme = Lead::factory()->create(['company_name' => 'Acme Corp']);
        $globex = Lead::factory()->create(['company_name' => 'Globex Inc']);
        Requirement::factory()->create(['lead_id' => $acme->id]);
        Requirement::factory()->create(['lead_id' => $globex->id]);

        $this->actingAs($user)->get(route('requirements.export-pdf', ['search' => 'Acme']))->assertOk();
    }
}
