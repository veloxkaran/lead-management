<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\BsDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPerformanceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_renders_the_widget_container_for_the_js_component_to_mount_on(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Performance Snapshot');
        $response->assertSee('id="performance-snapshot"', false);
    }

    public function test_daily_snapshot_scopes_every_metric_to_today_and_labels_it_with_the_bs_day(): void
    {
        $user = User::factory()->create();

        SupportTicket::factory()->create(['created_at' => now()]);
        SupportTicket::factory()->create(['created_at' => now(), 'resolved_at' => now()]);
        SupportTicket::factory()->create(['created_at' => now()->subDays(3)]);

        $requirementsLead = Lead::factory()->create(['created_at' => now()->subDays(3)]);
        Requirement::factory()->create(['lead_id' => $requirementsLead->id, 'created_at' => now()]);
        Requirement::factory()->create(['lead_id' => $requirementsLead->id, 'created_at' => now(), 'completed_at' => now()]);
        Requirement::factory()->create(['lead_id' => $requirementsLead->id, 'created_at' => now()->subDays(3)]);

        Lead::factory()->create(['created_at' => now()]);
        Lead::factory()->create(['created_at' => now(), 'achieved_at' => now()]);
        Lead::factory()->create(['created_at' => now()->subDays(3)]);

        $response = $this->actingAs($user)->getJson(route('dashboard.performance-snapshot', ['snapshot_period' => 'daily']));

        $response->assertOk();
        $response->assertJson([
            'period' => 'daily',
            'dateLabel' => BsDate::dayLabel(now()),
            'tickets' => ['created' => 2, 'solved' => 1],
            'requirements' => ['created' => 2, 'closed' => 1],
            'leads' => ['generated' => 2, 'converted' => 1, 'ratio' => 50.0],
        ]);
    }

    public function test_missing_period_param_defaults_to_daily(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('dashboard.performance-snapshot'));

        $response->assertOk();
        $response->assertJson(['period' => 'daily', 'dateLabel' => BsDate::dayLabel(now())]);
    }

    public function test_monthly_snapshot_scopes_to_the_whole_calendar_month_and_labels_it_with_the_bs_month(): void
    {
        $user = User::factory()->create();

        // Inside this month but not today.
        SupportTicket::factory()->create(['created_at' => now()->startOfMonth()->addDays(2)]);

        // Outside this month entirely.
        SupportTicket::factory()->create(['created_at' => now()->subMonthNoOverflow()]);

        $response = $this->actingAs($user)->getJson(route('dashboard.performance-snapshot', ['snapshot_period' => 'monthly']));

        $response->assertOk();
        $response->assertJson([
            'period' => 'monthly',
            'dateLabel' => BsDate::monthLabel(now()),
            'tickets' => ['created' => 1],
        ]);
    }

    public function test_lifetime_snapshot_is_unbounded_and_labels_since_the_earliest_record(): void
    {
        $user = User::factory()->create();

        $old = Lead::factory()->create(['created_at' => now()->subYears(2)]);
        Lead::factory()->create(['created_at' => now()]);

        $response = $this->actingAs($user)->getJson(route('dashboard.performance-snapshot', ['snapshot_period' => 'lifetime']));

        $response->assertOk();
        $response->assertJson([
            'period' => 'lifetime',
            'dateLabel' => BsDate::sinceLabel($old->created_at),
            'leads' => ['generated' => 2],
        ]);
    }

    public function test_conversion_ratio_is_null_when_no_leads_were_generated_in_the_window(): void
    {
        $user = User::factory()->create();

        Lead::factory()->create(['created_at' => now()->subDays(3)]);

        $response = $this->actingAs($user)->getJson(route('dashboard.performance-snapshot', ['snapshot_period' => 'daily']));

        $response->assertOk();
        $response->assertJson(['leads' => ['generated' => 0, 'converted' => 0, 'ratio' => null]]);
    }
}
