<?php

namespace Tests\Feature;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function memberWith(?array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['permissions' => $permissions])->save();

        return $user;
    }

    public function test_members_have_full_access_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->permissions);
        $this->assertFalse($user->hasRestrictedPermissions());
        foreach (PermissionModule::cases() as $module) {
            foreach ($module->actions() as $action) {
                $this->assertTrue($user->hasPermission($module, $action), "{$module->value}.{$action->value}");
            }
        }

        $this->actingAs($user)->get(route('leads.index'))->assertOk()->assertSee('Add Lead');
        $this->actingAs($user)->get(route('leads.create'))->assertOk();
    }

    public function test_revoking_view_blocks_the_whole_component_and_hides_it_from_the_sidebar(): void
    {
        $user = $this->memberWith(['leads' => []]);
        $lead = Lead::factory()->create();

        $this->actingAs($user)->get(route('leads.index'))->assertForbidden();
        $this->actingAs($user)->get(route('leads.show', $lead))->assertForbidden();
        $this->actingAs($user)->get(route('leads.create'))->assertForbidden();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('leads.index'), false)
            ->assertSee(route('raw-data.index'), false);
    }

    public function test_view_only_member_can_browse_but_not_create_edit_or_delete(): void
    {
        $user = $this->memberWith(['leads' => ['view']]);
        $lead = Lead::factory()->create(['created_by' => $user->id, 'assigned_user_id' => $user->id]);

        $this->actingAs($user)->get(route('leads.index'))->assertOk()->assertDontSee('Add Lead');
        $this->actingAs($user)->get(route('leads.show', $lead))->assertOk();

        $this->actingAs($user)->get(route('leads.create'))->assertForbidden();
        $this->actingAs($user)->post(route('leads.store'), ['company_name' => 'Nope'])->assertForbidden();
        $this->actingAs($user)->get(route('leads.edit', $lead))->assertForbidden();
        $this->actingAs($user)->put(route('leads.update', $lead), ['company_name' => 'Nope'])->assertForbidden();
        $this->actingAs($user)->post(route('leads.notes.store', $lead), ['comment' => 'Nope'])->assertForbidden();

        $this->assertDatabaseMissing('leads', ['company_name' => 'Nope']);
        $this->assertDatabaseMissing('lead_notes', ['comment' => 'Nope']);
    }

    public function test_policy_checks_respect_permissions_so_buttons_hide_automatically(): void
    {
        $user = $this->memberWith(['leads' => ['view', 'create']]);
        $lead = Lead::factory()->create();

        $this->assertTrue($user->can('view', $lead));
        $this->assertTrue($user->can('create', Lead::class));
        $this->assertFalse($user->can('update', $lead));
        $this->assertFalse($user->can('changeStatus', $lead));
        $this->assertFalse($user->can('delete', $lead));
    }

    public function test_permissions_never_grant_more_than_the_policy_allows(): void
    {
        // Full leads permissions, but LeadPolicy::delete() is Super Admin only.
        $user = User::factory()->create();

        $this->assertFalse($user->can('delete', Lead::factory()->create()));
    }

    public function test_an_explicit_route_action_overrides_the_inferred_one(): void
    {
        // Adding a note POSTs to a `store` method (inferred: create), but the
        // route declares it an edit of the lead.
        $user = $this->memberWith(['leads' => ['view', 'update']]);
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('leads.notes.store', $lead), ['comment' => 'Called the client'])->assertRedirect();

        $this->assertDatabaseHas('lead_notes', ['lead_id' => $lead->id, 'comment' => 'Called the client']);
    }

    public function test_commenting_needs_edit_permission_and_the_form_is_hidden_without_it(): void
    {
        $user = $this->memberWith(['requirements' => ['view']]);
        $requirement = Requirement::factory()->create();

        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertOk()
            ->assertDontSee(route('requirements.comments.store', $requirement), false);

        $this->actingAs($user)->post(route('requirements.comments.store', $requirement), ['comment' => 'Nope'])->assertForbidden();
        $this->assertDatabaseMissing('requirement_comments', ['comment' => 'Nope']);
    }

    public function test_delete_can_be_revoked_on_its_own(): void
    {
        $user = $this->memberWith(['tasks' => ['view', 'create', 'update']]);
        $task = Task::factory()->create(['created_by' => $user->id, 'assigned_by' => $user->id, 'assigned_to' => $user->id]);

        $this->actingAs($user)->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertForbidden();

        $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_other_actions_require_view(): void
    {
        $user = $this->memberWith(['tasks' => ['create', 'update', 'delete']]);

        $this->assertFalse($user->hasPermission(PermissionModule::Tasks, PermissionAction::Create));
        $this->actingAs($user)->get(route('tasks.create'))->assertForbidden();
    }

    public function test_super_admins_cannot_be_restricted(): void
    {
        $admin = $this->memberWith(['leads' => []], ['role' => UserRole::SuperAdmin]);

        $this->assertTrue($admin->hasPermission(PermissionModule::Leads, PermissionAction::Delete));
        $this->assertFalse($admin->hasRestrictedPermissions());
        $this->actingAs($admin)->get(route('leads.index'))->assertOk();

        $otherAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($otherAdmin)->get(route('users.permissions.edit', $admin))->assertForbidden();
    }

    public function test_super_admin_sees_the_permission_matrix_with_everything_ticked_by_default(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('users.permissions.edit', $member));

        $response->assertOk()->assertSee('full access');
        foreach (PermissionModule::cases() as $module) {
            $response->assertSee($module->label());
        }
        $response->assertSee('name="permissions[leads][]" value="delete" id="perm-leads-delete" aria-label="Delete Lead Management" data-permission-action="delete" checked', false);

        $this->actingAs($admin)->get(route('users.index'))->assertSee(route('users.permissions.edit', $member), false);
    }

    public function test_super_admin_can_restrict_a_member_and_only_restrictions_are_stored(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = User::factory()->create();

        $all = collect(PermissionModule::cases())
            ->mapWithKeys(fn (PermissionModule $m) => [$m->value => array_map(fn ($a) => $a->value, $m->actions())])
            ->all();

        $submitted = array_merge($all, [
            'leads' => ['view', 'update'],
            'reports' => [],
            // Create/Edit without View make no sense — stored as no access.
            'goals' => ['create', 'update'],
            // Actions a component doesn't have are ignored.
            'announcements' => ['view', 'create', 'delete'],
        ]);

        $this->actingAs($admin)
            ->put(route('users.permissions.update', $member), ['permissions' => $submitted])
            ->assertRedirect(route('users.permissions.edit', $member));

        $this->assertSame([
            'leads' => ['view', 'update'],
            'goals' => [],
            'reports' => [],
        ], $member->refresh()->permissions);
        $this->assertTrue($member->hasRestrictedPermissions());

        $this->actingAs($admin)->get(route('users.index'))->assertSee('Restricted');
    }

    public function test_saving_with_everything_ticked_resets_to_null(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = $this->memberWith(['leads' => ['view']]);

        $all = collect(PermissionModule::cases())
            ->mapWithKeys(fn (PermissionModule $m) => [$m->value => array_map(fn ($a) => $a->value, $m->actions())])
            ->all();

        $this->actingAs($admin)->put(route('users.permissions.update', $member), ['permissions' => $all]);

        $this->assertNull($member->refresh()->permissions);
    }

    public function test_saving_with_nothing_ticked_revokes_everything(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->put(route('users.permissions.update', $member), []);

        $member->refresh();
        foreach (PermissionModule::cases() as $module) {
            $this->assertFalse($member->hasPermission($module), $module->value);
        }
    }

    public function test_reset_restores_full_access(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = $this->memberWith(['leads' => []]);

        $this->actingAs($admin)->delete(route('users.permissions.destroy', $member))->assertRedirect();

        $this->assertNull($member->refresh()->permissions);
    }

    public function test_invalid_actions_are_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('users.permissions.update', $member), ['permissions' => ['leads' => ['view', 'launch']]])
            ->assertSessionHasErrors('permissions.leads.1');

        $this->assertNull($member->refresh()->permissions);
    }

    public function test_non_admins_cannot_manage_permissions(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $member = User::factory()->create();

        $this->actingAs($manager)->get(route('users.permissions.edit', $member))->assertForbidden();
        $this->actingAs($manager)->put(route('users.permissions.update', $member), ['permissions' => []])->assertForbidden();
        $this->actingAs($manager)->delete(route('users.permissions.destroy', $member))->assertForbidden();

        $this->assertNull($member->refresh()->permissions);
    }

    public function test_permissions_are_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $user->fill(['permissions' => ['leads' => []]])->save();

        $this->assertNull($user->refresh()->permissions);
    }
}
