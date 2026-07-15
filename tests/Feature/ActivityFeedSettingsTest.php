<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Settings\ActivityFeedSettings;
use App\Support\ActivityModules\ActivityModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityFeedSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_access_activity_feed_settings(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->get(route('activity-feed-settings.edit'))->assertForbidden();
    }

    public function test_super_admin_can_update_activity_feed_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->put(route('activity-feed-settings.update'), [
            'activity_feed_enabled' => '1',
            'activity_feed_refresh_seconds' => 30,
            'activity_feed_per_page' => 20,
            'modules' => ['lead', 'goal'],
        ])->assertRedirect();

        $this->assertSame('1', Setting::get('activity_feed_enabled'));
        $this->assertSame('30', Setting::get('activity_feed_refresh_seconds'));
        $this->assertSame('20', Setting::get('activity_feed_per_page'));
        $this->assertSame('lead,goal', Setting::get('activity_feed_enabled_modules'));
    }

    public function test_dashboard_hides_the_widget_when_globally_disabled(): void
    {
        $user = User::factory()->create();
        Setting::set('activity_feed_enabled', '0');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('activity-feed-widget');
    }

    public function test_dashboard_shows_the_widget_when_enabled(): void
    {
        $user = User::factory()->create();
        Setting::set('activity_feed_enabled', '1');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Activity Feed');
    }

    public function test_settings_class_defaults_when_nothing_is_configured(): void
    {
        $settings = app(ActivityFeedSettings::class);

        $this->assertTrue($settings->enabled());
        $this->assertSame(10, $settings->refreshSeconds());
        $this->assertSame(10, $settings->perPage());
        $this->assertSame(ActivityModuleRegistry::keys(), $settings->enabledModules());
    }

    public function test_settings_class_reflects_saved_values(): void
    {
        $settings = app(ActivityFeedSettings::class);

        $settings->save(refreshSeconds: 45, perPage: 25, modules: ['lead', 'meeting'], enabled: false);

        $this->assertFalse($settings->enabled());
        $this->assertSame(45, $settings->refreshSeconds());
        $this->assertSame(25, $settings->perPage());
        $this->assertSame(['lead', 'meeting'], $settings->enabledModules());
    }

    public function test_per_page_never_goes_below_one_even_if_a_bad_value_is_stored(): void
    {
        Setting::set('activity_feed_per_page', '-5');

        $this->assertSame(1, app(ActivityFeedSettings::class)->perPage());
    }
}
