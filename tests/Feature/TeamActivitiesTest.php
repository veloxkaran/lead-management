<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamActivitiesTest extends TestCase
{
    use RefreshDatabase;

    private function makeChain(): array
    {
        $company = Company::factory()->create();
        $make = fn (?User $manager = null) => User::factory()->create([
            'company_id' => $company->id,
            'reporting_manager_id' => $manager?->id,
        ]);

        $a = $make();
        $b = $make($a);
        $c = $make($b);
        $d = $make($c);

        return [$a, $b, $c, $d];
    }

    public function test_manager_of_a_manager_sees_activity_from_direct_and_indirect_reports(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();
        $stranger = User::factory()->create();

        Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $b->id, 'assigned_user_id' => $b->id, 'company_name' => 'From B']);
        Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $d->id, 'assigned_user_id' => $d->id, 'company_name' => 'From D']);
        Lead::factory()->create(['created_by' => $stranger->id, 'assigned_user_id' => $stranger->id, 'company_name' => 'From Stranger']);

        $response = $this->actingAs($a)->get(route('team.activities'));

        $response->assertOk();
        $response->assertSee('From B');
        $response->assertSee('From D');
        $response->assertDontSee('From Stranger');
    }

    public function test_a_leaf_ic_sees_the_empty_state(): void
    {
        [, , , $d] = $this->makeChain();

        $this->actingAs($d)->get(route('team.activities'))
            ->assertOk()
            ->assertSee('No activity yet');
    }

    public function test_overseer_sees_company_wide_activity_regardless_of_hierarchy(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::Manager]);
        User::factory()->create(['company_id' => $company->id, 'reporting_manager_id' => $manager->id]);
        $repOne = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::BusinessDevelopment]);
        $repTwo = User::factory()->create(['company_id' => $company->id, 'role' => UserRole::BusinessDevelopment]);

        Lead::factory()->create(['company_id' => $company->id, 'created_by' => $repOne->id, 'assigned_user_id' => $repOne->id, 'company_name' => 'Rep One Co']);
        Lead::factory()->create(['company_id' => $company->id, 'created_by' => $repTwo->id, 'assigned_user_id' => $repTwo->id, 'company_name' => 'Rep Two Co']);

        $response = $this->actingAs($manager)->get(route('team.activities'));

        $response->assertOk();
        $response->assertSee('Rep One Co');
        $response->assertSee('Rep Two Co');
    }

    public function test_user_filter_narrows_results_to_one_employee(): void
    {
        [$a, $b, $c] = $this->makeChain();

        Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $b->id, 'assigned_user_id' => $b->id, 'company_name' => 'From B']);
        Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $c->id, 'assigned_user_id' => $c->id, 'company_name' => 'From C']);

        $response = $this->actingAs($a)->get(route('team.activities', ['user_id' => $b->id]));

        $response->assertOk();
        $response->assertSee('From B');
        $response->assertDontSee('From C');
    }

    public function test_module_filter_narrows_to_one_activity_type(): void
    {
        [$a, $b] = $this->makeChain();

        $lead = Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $b->id, 'assigned_user_id' => $b->id, 'company_name' => 'Lead Co']);
        LeadNote::factory()->create(['company_id' => $a->company_id, 'lead_id' => $lead->id, 'author_id' => $b->id]);

        $response = $this->actingAs($a)->get(route('team.activities', ['module' => 'note']));

        $response->assertOk();
        $response->assertSee('added a note on Lead Co');
        $response->assertDontSee('created a new lead');
    }

    public function test_search_filter_matches_description(): void
    {
        [$a, $b] = $this->makeChain();

        Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $b->id, 'assigned_user_id' => $b->id, 'company_name' => 'Findable Widgets']);
        Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $b->id, 'assigned_user_id' => $b->id, 'company_name' => 'Other Co']);

        $response = $this->actingAs($a)->get(route('team.activities', ['search' => 'Findable']));

        $response->assertOk();
        $response->assertSee('Findable Widgets');
        $response->assertDontSee('Other Co');
    }

    public function test_date_range_filter_excludes_entries_outside_the_range(): void
    {
        [$a, $b] = $this->makeChain();

        $inRange = Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $b->id, 'assigned_user_id' => $b->id, 'company_name' => 'In Range Co']);
        $outOfRange = Lead::factory()->create(['company_id' => $a->company_id, 'created_by' => $b->id, 'assigned_user_id' => $b->id, 'company_name' => 'Out Of Range Co']);

        DB::table('activity_log_entries')->where('subject_type', Lead::class)->where('subject_id', $inRange->id)
            ->update(['created_at' => now()->subDays(2)]);
        DB::table('activity_log_entries')->where('subject_type', Lead::class)->where('subject_id', $outOfRange->id)
            ->update(['created_at' => now()->subDays(10)]);

        $response = $this->actingAs($a)->get(route('team.activities', [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('In Range Co');
        $response->assertDontSee('Out Of Range Co');
    }

    public function test_query_count_does_not_grow_with_team_size(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);

        $reports = User::factory()->count(2)->create([
            'company_id' => $company->id, 'reporting_manager_id' => $manager->id,
        ]);
        foreach ($reports as $report) {
            Lead::factory()->create(['company_id' => $company->id, 'created_by' => $report->id, 'assigned_user_id' => $report->id]);
        }

        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('team.activities'))->assertOk();
        $queryCountForTwo = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $moreReports = User::factory()->count(6)->create([
            'company_id' => $company->id, 'reporting_manager_id' => $manager->id,
        ]);
        foreach ($moreReports as $report) {
            Lead::factory()->create(['company_id' => $company->id, 'created_by' => $report->id, 'assigned_user_id' => $report->id]);
        }

        Cache::flush();
        $manager = $manager->fresh();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('team.activities'))->assertOk();
        $queryCountForEight = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForTwo, $queryCountForEight);
    }
}
