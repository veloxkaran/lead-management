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

    /**
     * BS and AD month boundaries don't line up (a BS month typically spans
     * the back half of one AD month into the front half of the next), so
     * "Monthly" has to scope its query to the current BS month's actual AD
     * date range — not the AD calendar month — to match the BS name it's
     * labeled with.
     */
    public function test_monthly_snapshot_scopes_to_the_current_bs_month_and_labels_it_accordingly(): void
    {
        $user = User::factory()->create();

        $bs = BsDate::toBsParts(now());
        [$start] = BsDate::monthToAdRange($bs['year'], $bs['month']);

        // Inside the current BS month but not necessarily today.
        SupportTicket::factory()->create(['created_at' => $start->copy()->addDay()]);

        // The day before the current BS month started — outside it entirely.
        SupportTicket::factory()->create(['created_at' => $start->copy()->subDay()]);

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

    /**
     * The Lifetime period has no from/to, so $countBetween's whereBetween
     * never runs — without an explicit whereNotNull, "Solved"/"Closed"/
     * "Converted" fell back to a bare count() of the whole table (e.g.
     * every ticket, resolved or not), instead of only ones that actually
     * have a resolved_at/completed_at/achieved_at set.
     */
    public function test_lifetime_snapshot_only_counts_solved_closed_converted_not_every_record(): void
    {
        $user = User::factory()->create();

        SupportTicket::factory()->create(['resolved_at' => now()]);
        SupportTicket::factory()->create(['resolved_at' => null]);
        SupportTicket::factory()->create(['resolved_at' => null]);

        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'completed_at' => now()]);
        Requirement::factory()->create(['lead_id' => $lead->id, 'completed_at' => null]);

        Lead::factory()->create(['achieved_at' => now()]);
        Lead::factory()->create(['achieved_at' => null]);
        Lead::factory()->create(['achieved_at' => null]);

        $response = $this->actingAs($user)->getJson(route('dashboard.performance-snapshot', ['snapshot_period' => 'lifetime']));

        $response->assertOk();
        $response->assertJson([
            'period' => 'lifetime',
            'tickets' => ['created' => 3, 'solved' => 1],
            'requirements' => ['created' => 2, 'closed' => 1],
            'leads' => ['generated' => 4, 'converted' => 1],
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

    /**
     * A ticket/requirement resolved and then reopened must stop counting
     * as "Solved"/"Closed" on the snapshot — this drives the reopen through
     * the real update endpoints (which clear resolved_at/completed_at, see
     * RequirementStatusChangeTest/SupportTicketStatusLogTest) rather than
     * setting the DB state directly, so it proves the snapshot no longer
     * relies on a timestamp that survives reopening.
     */
    public function test_a_reopened_ticket_and_requirement_stop_counting_as_solved_closed(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $ticket = SupportTicket::factory()->create([
            'created_at' => now(),
            'status' => \App\Enums\RequirementStatus::Completed,
            'resolved_at' => now(),
        ]);
        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => \App\Enums\RequirementStatus::InProgress->value,
        ])->assertRedirect();

        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create([
            'lead_id' => $lead->id,
            'created_at' => now(),
            'status' => \App\Enums\RequirementStatus::Completed,
            'completed_at' => now(),
        ]);
        $this->actingAs($superAdmin)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => \App\Enums\RequirementStatus::InProgress->value,
        ])->assertRedirect();

        $response = $this->actingAs($superAdmin)->getJson(route('dashboard.performance-snapshot', ['snapshot_period' => 'daily']));

        $response->assertOk();
        $response->assertJson([
            'tickets' => ['created' => 1, 'solved' => 0],
            'requirements' => ['created' => 1, 'closed' => 0],
        ]);
    }
}
