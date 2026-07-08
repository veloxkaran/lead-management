<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_view_login_page(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_active_users_can_authenticate(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    public function test_suspended_users_cannot_authenticate(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Suspended]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }

    public function test_non_super_admin_cannot_access_user_management(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($user)->get('/users')->assertForbidden();
    }
}
