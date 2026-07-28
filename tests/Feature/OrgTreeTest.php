<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrgTreeTest extends TestCase
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

    public function test_renders_the_nested_hierarchy_for_a_manager_with_reports(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();

        $response = $this->actingAs($a)->get(route('org-tree.index'));

        $response->assertOk();
        $response->assertSeeInOrder([$a->name, $b->name, $c->name, $d->name]);
    }

    public function test_a_leaf_ic_with_no_reports_is_forbidden(): void
    {
        [, , , $d] = $this->makeChain();

        $this->actingAs($d)->get(route('org-tree.index'))->assertForbidden();
    }

    public function test_query_count_does_not_grow_with_employee_count(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);

        User::factory()->count(3)->create([
            'company_id' => $company->id, 'reporting_manager_id' => $manager->id,
        ]);
        // Flushed before each measured request: any unrelated cross-cutting
        // cache keyed by this same manager's id would otherwise warm up
        // between the two calls and shrink the second call's query count
        // for reasons that have nothing to do with team size.
        Cache::flush();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('org-tree.index'))->assertOk();
        $queryCountForFew = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        User::factory()->count(10)->create([
            'company_id' => $company->id, 'reporting_manager_id' => $manager->id,
        ]);
        Cache::flush();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('org-tree.index'))->assertOk();
        $queryCountForMany = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForFew, $queryCountForMany);
    }
}
