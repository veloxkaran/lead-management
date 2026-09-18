<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPerformanceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_daily_view_scopes_every_metric_to_today(): void
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

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('snapshotPeriod', 'daily');
        $response->assertViewHas('ticketsCreatedSnapshot', 2);
        $response->assertViewHas('ticketsSolvedSnapshot', 1);
        $response->assertViewHas('requirementsCreatedSnapshot', 2);
        $response->assertViewHas('requirementsClosedSnapshot', 1);
        $response->assertViewHas('leadsGeneratedSnapshot', 2);
        $response->assertViewHas('leadsConvertedSnapshot', 1);
        $response->assertViewHas('leadsConversionRatioSnapshot', 50.0);
        $response->assertSee('Performance Snapshot');
    }

    public function test_monthly_switch_scopes_metrics_to_the_whole_calendar_month(): void
    {
        $user = User::factory()->create();

        // Inside this month but not today.
        $earlierThisMonth = now()->startOfMonth()->addDays(2);
        SupportTicket::factory()->create(['created_at' => $earlierThisMonth]);

        // Outside this month entirely.
        SupportTicket::factory()->create(['created_at' => now()->subMonthNoOverflow()]);

        $response = $this->actingAs($user)->get(route('dashboard', ['snapshot_period' => 'monthly']));

        $response->assertOk();
        $response->assertViewHas('snapshotPeriod', 'monthly');
        $response->assertViewHas('ticketsCreatedSnapshot', 1);
    }

    public function test_conversion_ratio_is_null_when_no_leads_were_generated_in_the_window(): void
    {
        $user = User::factory()->create();

        Lead::factory()->create(['created_at' => now()->subDays(3)]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('leadsGeneratedSnapshot', 0);
        $response->assertViewHas('leadsConversionRatioSnapshot', null);
        $response->assertSee('—');
    }

    public function test_daily_and_monthly_toggle_links_preserve_the_whats_new_today_filter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => 'week']));

        $response->assertOk();
        $response->assertSee(route('dashboard', ['period' => 'week', 'snapshot_period' => 'daily']));
        $response->assertSee(route('dashboard', ['period' => 'week', 'snapshot_period' => 'monthly']));
    }
}
