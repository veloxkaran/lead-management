<?php

namespace Tests\Feature;

use App\Enums\UiTheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_get_the_light_blue_theme_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UiTheme::LightBlue, $user->uiTheme());

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-app-theme="light-blue"', false)
            ->assertSee('data-bs-theme="light"', false);
    }

    public function test_the_top_bar_offers_every_theme(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertSee(route('preferences.theme.update'), false);
        foreach (UiTheme::cases() as $theme) {
            $response->assertSee('value="'.$theme->value.'"', false);
            $response->assertSee($theme->label());
        }
    }

    public function test_a_user_can_switch_theme_and_it_is_remembered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->patch(route('preferences.theme.update'), ['theme' => 'dark'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(UiTheme::Dark, $user->refresh()->theme);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('data-app-theme="dark"', false)
            ->assertSee('data-bs-theme="dark"', false);
    }

    public function test_an_unknown_theme_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('preferences.theme.update'), ['theme' => 'neon'])
            ->assertSessionHasErrors('theme');

        $this->assertNull($user->refresh()->theme);
    }

    public function test_guests_cannot_change_the_theme(): void
    {
        $this->patch(route('preferences.theme.update'), ['theme' => 'dark'])->assertRedirect(route('login'));
    }

    public function test_theme_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $user->fill(['theme' => 'dark'])->save();

        $this->assertNull($user->refresh()->theme);
    }
}
