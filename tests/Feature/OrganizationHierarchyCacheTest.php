<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Services\OrganizationHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OrganizationHierarchyCacheTest extends TestCase
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

    public function test_subordinate_ids_are_cached_across_calls(): void
    {
        [$a] = $this->makeChain();
        $service = app(OrganizationHierarchyService::class);

        $service->getAllSubordinateIds($a);

        $this->assertTrue(Cache::has("org_hierarchy:subordinate_ids:{$a->id}"));
    }

    public function test_reassigning_a_user_forgets_both_the_old_and_new_ancestor_caches(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();
        $service = app(OrganizationHierarchyService::class);

        // Warm every ancestor's cache.
        $service->getAllSubordinateIds($a);
        $service->getAllSubordinateIds($b);
        $service->getAllSubordinateIds($c);

        $this->assertTrue(Cache::has("org_hierarchy:subordinate_ids:{$a->id}"));
        $this->assertTrue(Cache::has("org_hierarchy:subordinate_ids:{$b->id}"));
        $this->assertTrue(Cache::has("org_hierarchy:subordinate_ids:{$c->id}"));

        // Move D from reporting to C, to reporting directly to B.
        $d->update(['reporting_manager_id' => $b->id]);

        // Old chain (C, and C's own ancestors A/B) must be forgotten...
        $this->assertFalse(Cache::has("org_hierarchy:subordinate_ids:{$c->id}"));
        $this->assertFalse(Cache::has("org_hierarchy:subordinate_ids:{$a->id}"));
        // ...as must the new chain (B is now D's direct manager).
        $this->assertFalse(Cache::has("org_hierarchy:subordinate_ids:{$b->id}"));

        // And the resolved data reflects the move.
        $this->assertTrue($service->getAllSubordinateIds($b)->contains($d->id));
        $this->assertFalse($service->getAllSubordinateIds($c)->contains($d->id));
    }

    public function test_changing_an_unrelated_attribute_forgets_nothing(): void
    {
        [$a, $b] = $this->makeChain();
        $service = app(OrganizationHierarchyService::class);

        $service->getAllSubordinateIds($a);
        $this->assertTrue(Cache::has("org_hierarchy:subordinate_ids:{$a->id}"));

        $b->update(['name' => 'Renamed']);

        $this->assertTrue(Cache::has("org_hierarchy:subordinate_ids:{$a->id}"));
    }
}
