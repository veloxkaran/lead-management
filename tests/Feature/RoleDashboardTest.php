<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider roleProvider
     */
    public function test_each_role_gets_its_own_dashboard(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public static function roleProvider(): array
    {
        return [
            'super admin' => [UserRole::SuperAdmin],
            'manager' => [UserRole::Manager],
            'business development' => [UserRole::BusinessDevelopment],
            'customer success' => [UserRole::CustomerSuccess],
            'finance' => [UserRole::Finance],
        ];
    }
}
