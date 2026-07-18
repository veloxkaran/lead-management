<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeChain(): array
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $make = fn (?User $manager = null) => User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'reporting_manager_id' => $manager?->id,
        ]);

        $a = $make();
        $b = $make($a);
        $c = $make($b);
        $d = $make($c);

        return [$a, $b, $c, $d];
    }

    public function test_manager_sees_exactly_their_subordinates_not_a_stranger(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();
        $stranger = User::factory()->create();

        $response = $this->actingAs($a)->get(route('team.index'));

        $response->assertOk();
        $response->assertSee($b->name);
        $response->assertSee($c->name);
        $response->assertSee($d->name);
        $response->assertDontSee($stranger->name);
    }

    public function test_a_leaf_ic_sees_the_empty_state(): void
    {
        [, , , $d] = $this->makeChain();

        $this->actingAs($d)->get(route('team.index'))
            ->assertOk()
            ->assertSee('No team members yet');
    }

    public function test_search_filters_by_name(): void
    {
        [$a, $b] = $this->makeChain();
        $b->update(['name' => 'Findable Person']);

        $response = $this->actingAs($a)->get(route('team.index', ['search' => 'Findable']));

        $response->assertOk()->assertSee('Findable Person');
    }

    public function test_department_filter_narrows_results(): void
    {
        [$a, $b, $c] = $this->makeChain();
        $otherDepartment = Department::factory()->create(['company_id' => $a->company_id]);
        $c->update(['department_id' => $otherDepartment->id]);

        $response = $this->actingAs($a)->get(route('team.index', ['department_id' => $otherDepartment->id]));

        $response->assertOk();
        $response->assertSee($c->name);
        $response->assertDontSee($b->name);
    }

    public function test_pagination_is_applied(): void
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $manager = User::factory()->create(['company_id' => $company->id, 'department_id' => $department->id]);
        User::factory()->count(20)->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'reporting_manager_id' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->get(route('team.index'));

        $response->assertOk();
        $response->assertViewHas('members', fn ($members) => $members->lastPage() === 2);
    }

    public function test_query_count_does_not_grow_with_team_size(): void
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $manager = User::factory()->create(['company_id' => $company->id, 'department_id' => $department->id]);

        User::factory()->count(2)->create([
            'company_id' => $company->id, 'department_id' => $department->id, 'reporting_manager_id' => $manager->id,
        ]);
        // Flushed before each measured request: unrelated cross-cutting
        // caches (e.g. the pre-existing policy-acknowledgment throttle
        // cache, keyed by this same manager's id) would otherwise warm up
        // between the two calls and shrink the second call's query count
        // for reasons that have nothing to do with team size.
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('team.index'))->assertOk();
        $queryCountForTwo = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        User::factory()->count(6)->create([
            'company_id' => $company->id, 'department_id' => $department->id, 'reporting_manager_id' => $manager->id,
        ]);
        Cache::flush();
        // Refetch: actingAs() otherwise reuses the exact same PHP object
        // across both calls, so a relation lazily loaded on the first
        // request (assignedDepartment, from the page header) stays cached
        // on that object for the second — an artifact of reusing one
        // in-memory instance across simulated requests, not something that
        // happens across two genuinely separate real HTTP requests.
        $manager = $manager->fresh();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('team.index'))->assertOk();
        $queryCountForEight = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForTwo, $queryCountForEight);
    }
}
