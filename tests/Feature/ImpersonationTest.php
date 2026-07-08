<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_start_impersonating_a_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['status' => UserStatus::Active]);

        $response = $this->actingAs($admin)->post(route('users.impersonate', $target));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($target);
        $this->assertEquals($admin->id, session('impersonator_id'));
    }

    public function test_impersonated_session_can_return_to_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('users.impersonate', $target));

        $response = $this->post(route('impersonate.stop'));

        $response->assertRedirect(route('users.index'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_regular_user_cannot_start_impersonation(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->post(route('users.impersonate', $target))->assertForbidden();
    }

    public function test_super_admin_cannot_impersonate_another_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('users.impersonate', $otherAdmin))->assertForbidden();
    }

    public function test_super_admin_cannot_impersonate_a_suspended_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['status' => UserStatus::Suspended]);

        $this->actingAs($admin)->post(route('users.impersonate', $target))->assertForbidden();
    }

    public function test_an_impersonated_session_cannot_start_a_nested_impersonation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $targetOne = User::factory()->create();
        $targetTwo = User::factory()->create();

        $this->actingAs($admin)->post(route('users.impersonate', $targetOne));

        // The active session is now targetOne, a non-admin — the super_admin
        // middleware blocks reaching the impersonate route at all, which is
        // what actually prevents nested impersonation.
        $response = $this->post(route('users.impersonate', $targetTwo));

        $response->assertForbidden();
        $this->assertAuthenticatedAs($targetOne);
    }
}
