<?php

namespace Tests\Feature;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementListTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_requirements_directly_with_their_company_name(): void
    {
        $user = User::factory()->create();
        $acme = Lead::factory()->create(['company_name' => 'Acme Corp']);
        $globex = Lead::factory()->create(['company_name' => 'Globex Inc']);
        Requirement::factory()->create(['lead_id' => $acme->id, 'title' => 'Acme login page']);
        Requirement::factory()->create(['lead_id' => $globex->id, 'title' => 'Globex reports']);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('requirements'));
        $response->assertSee('Acme login page');
        $response->assertSee('Globex reports');
        $response->assertSee(route('requirements.company', $acme), false);
        $response->assertSee('Generated Time');
        $response->assertDontSee('Generated:');
    }

    public function test_generated_time_column_matches_support_tickets(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create([
            'lead_id' => $lead->id,
            'status' => RequirementStatus::Completed,
            'created_at' => '2026-09-01 09:00:00',
            'completed_at' => '2026-09-02 11:30:00',
        ]);
        $open = Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending]);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertSee('Generated Time');
        $response->assertSee('Solved in 1 days, 2 hour and 30 min');
        $response->assertSee("ticketElapsed('".$open->created_at->toIso8601String()."')", false);
        $response->assertDontSee('Open for');
        $response->assertDontSee('Solved:');
    }

    public function test_index_can_be_filtered_by_company(): void
    {
        $user = User::factory()->create();
        $acme = Lead::factory()->create(['company_name' => 'Acme Corp']);
        $globex = Lead::factory()->create(['company_name' => 'Globex Inc']);
        Requirement::factory()->create(['lead_id' => $acme->id, 'title' => 'Acme login page']);
        Requirement::factory()->create(['lead_id' => $globex->id, 'title' => 'Globex reports']);

        $response = $this->actingAs($user)->get(route('requirements.index', ['lead_id' => $acme->id]));

        $this->assertSame(['Acme login page'], $response->viewData('requirements')->pluck('title')->all());
        $this->assertSame(1, $response->viewData('summary')->total);
    }

    public function test_company_filter_only_offers_companies_that_have_requirements(): void
    {
        $user = User::factory()->create();
        Requirement::factory()->create(['lead_id' => Lead::factory()->create(['company_name' => 'Has Requirements Co'])->id]);
        Lead::factory()->create(['company_name' => 'No Requirements Co']);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $this->assertSame(['Has Requirements Co'], $response->viewData('companies')->pluck('company_name')->all());
    }

    public function test_search_matches_requirement_text_as_well_as_company_name(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp']);
        Requirement::factory()->create(['lead_id' => $lead->id, 'title' => 'Invoice export']);
        Requirement::factory()->create(['lead_id' => $lead->id, 'title' => 'Login page']);

        $response = $this->actingAs($user)->get(route('requirements.index', ['search' => 'invoice']));

        $this->assertSame(['Invoice export'], $response->viewData('requirements')->pluck('title')->all());
    }

    public function test_quick_views_narrow_the_list_but_summary_counts_stay_whole(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'title' => 'Done one', 'status' => RequirementStatus::Completed]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'title' => 'Late one', 'status' => RequirementStatus::Pending, 'due_date' => now()->subDays(2)]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'title' => 'Future one', 'status' => RequirementStatus::InProgress, 'due_date' => now()->addDays(5)]);

        $titles = fn (string $view) => $this->actingAs($user)->get(route('requirements.index', ['view' => $view]))
            ->viewData('requirements')->pluck('title')->sort()->values()->all();

        $this->assertSame(['Future one', 'Late one'], $titles('open'));
        $this->assertSame(['Late one'], $titles('overdue'));
        $this->assertSame(['Done one'], $titles('completed'));

        $summary = $this->actingAs($user)->get(route('requirements.index', ['view' => 'overdue']))->viewData('summary');
        $this->assertSame([3, 2, 1, 1], [$summary->total, $summary->open, $summary->overdue, $summary->completed]);
    }

    public function test_summary_counts_follow_the_active_filters(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'priority' => RequirementPriority::High, 'status' => RequirementStatus::Completed]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'priority' => RequirementPriority::High, 'status' => RequirementStatus::Pending]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'priority' => RequirementPriority::Low, 'status' => RequirementStatus::Pending]);

        $summary = $this->actingAs($user)->get(route('requirements.index', ['priority' => 'high']))->viewData('summary');

        $this->assertSame(2, $summary->total);
        $this->assertSame(50, $summary->completionPercent());
    }

    public function test_pagination_is_by_requirement(): void
    {
        $user = User::factory()->create();
        Requirement::factory()->count(30)->create(['lead_id' => Lead::factory()->create()->id]);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $this->assertCount(25, $response->viewData('requirements'));
        $this->assertSame(30, $response->viewData('requirements')->total());
    }

    public function test_index_shows_one_row_per_requirement_without_expandable_details(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => Lead::factory()->create()->id,
            'title' => 'Short title',
            'requirement' => '<p>'.str_repeat('Detailed text. ', 10).'THE-FULL-ENDING</p>',
        ]);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertSee('Short title');
        $response->assertDontSee('THE-FULL-ENDING');
        $response->assertDontSee('Expand all');
        $response->assertSee('statusModal-'.$requirement->id, false);
    }
}
