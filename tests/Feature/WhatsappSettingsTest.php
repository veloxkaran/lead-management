<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Settings\WhatsappSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_access_whatsapp_settings(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->get(route('whatsapp-settings.edit'))->assertForbidden();
    }

    public function test_super_admin_can_update_whatsapp_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->put(route('whatsapp-settings.update'), [
            'whatsapp_enabled' => '1',
            'whatsapp_phone_number_id' => '1234567890',
            'whatsapp_business_account_id' => '999',
            'whatsapp_access_token' => 'secret-token',
            'whatsapp_webhook_verify_token' => 'verify-me',
            'whatsapp_app_secret' => 'app-secret',
        ])->assertRedirect();

        $this->assertSame('1', Setting::get('whatsapp_enabled'));
        $this->assertSame('1234567890', Setting::get('whatsapp_phone_number_id'));
        $this->assertSame('999', Setting::get('whatsapp_business_account_id'));
        $this->assertSame('secret-token', Setting::get('whatsapp_access_token'));
        $this->assertSame('verify-me', Setting::get('whatsapp_webhook_verify_token'));
        $this->assertSame('app-secret', Setting::get('whatsapp_app_secret'));
    }

    public function test_omitting_the_enabled_checkbox_disables_the_integration(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        Setting::set('whatsapp_enabled', '1');

        $this->actingAs($superAdmin)->put(route('whatsapp-settings.update'), [])->assertRedirect();

        $this->assertSame('0', Setting::get('whatsapp_enabled'));
    }

    public function test_settings_class_defaults_to_disabled_and_null_credentials(): void
    {
        $settings = app(WhatsappSettings::class);

        $this->assertFalse($settings->enabled());
        $this->assertNull($settings->phoneNumberId());
        $this->assertNull($settings->accessToken());
    }

    public function test_settings_class_reflects_saved_values(): void
    {
        $settings = app(WhatsappSettings::class);

        $settings->save([
            'whatsapp_phone_number_id' => '111',
            'whatsapp_business_account_id' => '222',
            'whatsapp_access_token' => 'token',
            'whatsapp_webhook_verify_token' => 'verify',
            'whatsapp_app_secret' => 'secret',
        ], enabled: true);

        $this->assertTrue($settings->enabled());
        $this->assertSame('111', $settings->phoneNumberId());
        $this->assertSame('222', $settings->businessAccountId());
        $this->assertSame('token', $settings->accessToken());
        $this->assertSame('verify', $settings->webhookVerifyToken());
        $this->assertSame('secret', $settings->appSecret());
        $this->assertStringContainsString('111/send-template', $settings->graphApiUrl('send-template'));
        $this->assertStringContainsString('222/message_templates', $settings->businessAccountUrl('message_templates'));
    }
}
