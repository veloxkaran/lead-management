<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Rules\ProhibitsHierarchyCycles;
use App\Services\OrganizationHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationHierarchyServiceTest extends TestCase
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

    public function test_get_all_subordinate_ids_resolves_the_full_chain_recursively(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();
        $service = app(OrganizationHierarchyService::class);

        $this->assertEqualsCanonicalizing([$b->id, $c->id, $d->id], $service->getAllSubordinateIds($a)->all());
        $this->assertEqualsCanonicalizing([$d->id], $service->getAllSubordinateIds($c)->all());
        $this->assertTrue($service->getAllSubordinateIds($d)->isEmpty());
    }

    public function test_get_manager_chain_walks_upward_nearest_first(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();
        $service = app(OrganizationHierarchyService::class);

        $this->assertSame([$c->id, $b->id, $a->id], $service->getManagerChain($d)->pluck('id')->all());
        $this->assertTrue($service->getManagerChain($a)->isEmpty());
    }

    public function test_visible_user_ids_for_overseer_manager_and_leaf_ic(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();
        $a->update(['role' => UserRole::SuperAdmin]);
        $otherCompanyUser = User::factory()->create(); // different (null) company_id — must not appear
        $service = app(OrganizationHierarchyService::class);

        // SuperAdmin (overseer): existing flat company-wide behavior, unchanged
        // — sees every user in their own company, not users in another company.
        $visibleToA = $service->visibleUserIds($a->fresh());
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id, $d->id], $visibleToA->all());
        $this->assertFalse($visibleToA->contains($otherCompanyUser->id));

        // B (non-overseer with reports): self + recursive subordinates only.
        $this->assertEqualsCanonicalizing([$b->id, $c->id, $d->id], $service->visibleUserIds($b)->all());

        // D (leaf IC): only self.
        $this->assertEqualsCanonicalizing([$d->id], $service->visibleUserIds($d)->all());
    }

    public function test_can_view_respects_the_hierarchy_in_both_directions(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();
        $service = app(OrganizationHierarchyService::class);

        $this->assertTrue($service->canView($a, $d));
        $this->assertFalse($service->canView($d, $a));
        $this->assertTrue($service->canView($b, $b));
    }

    public function test_a_hierarchy_cycle_is_rejected_at_write_time(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();

        $rule = new ProhibitsHierarchyCycles($a->id);
        $failed = false;

        $rule->validate('reporting_manager_id', $d->id, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Assigning A to report to D (its own subordinate) should have been rejected.');
    }

    public function test_organization_tree_nests_users_correctly(): void
    {
        [$a, $b, $c, $d] = $this->makeChain();
        $service = app(OrganizationHierarchyService::class);

        $tree = $service->getOrganizationTree($a->company_id);

        $this->assertCount(1, $tree);
        $this->assertSame($a->id, $tree[0]['user']->id);
        $this->assertSame($b->id, $tree[0]['children'][0]['user']->id);
        $this->assertSame($c->id, $tree[0]['children'][0]['children'][0]['user']->id);
        $this->assertSame($d->id, $tree[0]['children'][0]['children'][0]['children'][0]['user']->id);

        $subtree = $service->getOrganizationTree($a->company_id, $c->id);
        $this->assertCount(1, $subtree);
        $this->assertSame($c->id, $subtree[0]['user']->id);
        $this->assertSame($d->id, $subtree[0]['children'][0]['user']->id);
    }
}
