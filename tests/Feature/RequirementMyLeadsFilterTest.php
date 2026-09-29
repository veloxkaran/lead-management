<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RequirementMyLeadsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_leads_filter_only_lists_requirements_of_leads_assigned_to_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Lead::factory()->create(['company_name' => 'Mine Co', 'assigned_user_id' => $user->id]);
        $theirs = Lead::factory()->create(['company_name' => 'Theirs Co', 'assigned_user_id' => $other->id]);
        Requirement::factory()->create(['lead_id' => $mine->id]);
        Requirement::factory()->create(['lead_id' => $theirs->id]);

        $response = $this->actingAs($user)->get(route('requirements.index', ['my_leads' => 1]));

        $response->assertOk();
        $this->assertSame(['Mine Co'], $response->viewData('requirements')->pluck('lead.company_name')->all());
    }

    public function test_without_my_leads_every_leads_requirements_are_listed(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Requirement::factory()->create(['lead_id' => Lead::factory()->create(['company_name' => 'Mine Co', 'assigned_user_id' => $user->id])->id]);
        Requirement::factory()->create(['lead_id' => Lead::factory()->create(['company_name' => 'Theirs Co', 'assigned_user_id' => $other->id])->id]);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $this->assertEqualsCanonicalizing(['Mine Co', 'Theirs Co'], $response->viewData('requirements')->pluck('lead.company_name')->all());
    }

    public function test_my_leads_shows_a_dedicated_empty_state(): void
    {
        $user = User::factory()->create();
        Requirement::factory()->create(['lead_id' => Lead::factory()->create(['assigned_user_id' => User::factory()->create()->id])->id]);

        $response = $this->actingAs($user)->get(route('requirements.index', ['my_leads' => 1]));

        $response->assertOk();
        $response->assertSee('None of your leads have requirements');
    }

    public function test_pdf_export_respects_the_my_leads_filter(): void
    {
        $user = User::factory()->create();
        $mine = Lead::factory()->create(['company_name' => 'Mine Co', 'assigned_user_id' => $user->id]);
        $theirs = Lead::factory()->create(['company_name' => 'Theirs Co', 'assigned_user_id' => User::factory()->create()->id]);
        Requirement::factory()->create(['lead_id' => $mine->id, 'title' => 'Mine requirement']);
        Requirement::factory()->create(['lead_id' => $theirs->id, 'title' => 'Theirs requirement']);

        $captured = null;
        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function (string $view, array $data) use (&$captured, $pdf) {
            $captured = $data;

            return $pdf;
        });

        $this->actingAs($user)->get(route('requirements.export-pdf', ['my_leads' => 1]))->assertOk();

        $this->assertSame(['Mine requirement'], $captured['requirements']->pluck('title')->all());
    }

    public function test_index_summary_counts_cover_every_requirement_when_unfiltered(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Completed, 'created_at' => '2026-09-01 09:00:00', 'completed_at' => '2026-09-01 12:00:00']);
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending, 'due_date' => now()->subDays(3)]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::InProgress, 'due_date' => now()->addDays(3)]);

        $summary = $this->actingAs($user)->get(route('requirements.index'))->viewData('summary');

        $this->assertSame(3, $summary->total);
        $this->assertSame(2, $summary->open);
        $this->assertSame(1, $summary->overdue);
        $this->assertSame(1, $summary->completed);
        $this->assertSame(33, $summary->completionPercent());
        $this->assertSame('0 days, 3 hour and 0 min', $summary->avgSolvingTime);
    }
}
