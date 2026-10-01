<?php

namespace Tests\Feature;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Support\CampaignSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role, ?array $permissions = null): User
    {
        $user = User::factory()->create(['role' => $role]);

        if ($permissions !== null) {
            $user->forceFill(['permissions' => $permissions])->save();
        }

        return $user;
    }

    private function payload(): array
    {
        return ['name' => 'Promo', 'channel' => 'email', 'subject' => 'Hi', 'message' => 'Hello', 'audience' => 'none', 'extra_contacts' => 'a@example.com'];
    }

    public static function outreachRoles(): array
    {
        return [[UserRole::SuperAdmin], [UserRole::Manager], [UserRole::BusinessDevelopment]];
    }

    public static function otherRoles(): array
    {
        return [[UserRole::CustomerSuccess], [UserRole::Finance]];
    }

    #[DataProvider('outreachRoles')]
    public function test_outreach_roles_can_open_and_create_campaigns(UserRole $role): void
    {
        Queue::fake();
        $user = $this->user($role);

        $this->actingAs($user)->get(route('campaigns.index'))->assertOk()->assertSee(route('campaigns.create'));
        $this->actingAs($user)->get(route('campaigns.create'))->assertOk();
        $this->actingAs($user)->postCampaign($this->payload())->assertRedirect();
        $this->actingAs($user)->get(route('dashboard'))->assertSee(route('campaigns.index'));

        $this->assertSame(1, Campaign::count());
    }

    #[DataProvider('otherRoles')]
    public function test_other_roles_can_view_campaigns_but_not_create_them(UserRole $role): void
    {
        $user = $this->user($role);
        $campaign = Campaign::factory()->create(['name' => 'Team Campaign']);

        $this->actingAs($user)->get(route('campaigns.index'))->assertOk()->assertSee('Team Campaign')->assertDontSee(route('campaigns.create'));
        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertSee(route('campaigns.index'));
        $this->actingAs($user)->get(route('campaigns.create'))->assertForbidden();
        $this->actingAs($user)->postCampaign($this->payload())->assertForbidden();
        $this->actingAs($user)->postJson(route('campaigns.preview'), $this->payload())->assertForbidden();
    }

    public function test_everyone_permitted_to_view_sees_every_campaign_but_only_manages_their_own(): void
    {
        $me = $this->user(UserRole::BusinessDevelopment);
        $mine = Campaign::factory()->create(['name' => 'My Campaign', 'created_by' => $me->id, 'status' => CampaignStatus::Sending]);
        $theirs = Campaign::factory()->create(['name' => 'Someone Else Campaign', 'status' => CampaignStatus::Sending]);

        $this->actingAs($me)->get(route('campaigns.index'))->assertSee('My Campaign')->assertSee('Someone Else Campaign');
        $this->actingAs($me)->get(route('campaigns.show', $theirs))->assertOk()->assertDontSee('Cancel Campaign')->assertDontSee(route('campaigns.pause', $theirs));
        $this->actingAs($me)->get(route('campaigns.export', $theirs))->assertOk();
        $this->actingAs($me)->post(route('campaigns.pause', $theirs))->assertForbidden();
        $this->actingAs($me)->post(route('campaigns.cancel', $theirs))->assertForbidden();

        $this->actingAs($me)->get(route('campaigns.show', $mine))->assertOk()->assertSee('Cancel Campaign');
        $this->actingAs($me)->post(route('campaigns.pause', $mine))->assertSessionHas('success');
    }

    public function test_members_without_the_view_permission_cannot_see_campaigns(): void
    {
        $user = $this->user(UserRole::Finance, ['campaigns' => []]);
        $campaign = Campaign::factory()->create();

        $this->actingAs($user)->get(route('campaigns.index'))->assertForbidden();
        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertForbidden();
        $this->actingAs($user)->get(route('campaigns.export', $campaign))->assertForbidden();
        $this->actingAs($user)->get(route('campaigns.email-preview', $campaign))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('campaigns.index'));
    }

    public function test_managers_and_super_admins_see_every_campaign(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Team Campaign']);

        foreach ([UserRole::Manager, UserRole::SuperAdmin] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get(route('campaigns.index'))->assertSee('Team Campaign');
            $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertOk();
        }
    }

    public function test_view_only_permission_hides_and_blocks_creating(): void
    {
        $user = $this->user(UserRole::BusinessDevelopment, ['campaigns' => ['view']]);

        $this->actingAs($user)->get(route('campaigns.index'))->assertOk()->assertDontSee(route('campaigns.create'));
        $this->actingAs($user)->get(route('campaigns.create'))->assertForbidden();
        $this->actingAs($user)->postCampaign($this->payload())->assertForbidden();
        $this->actingAs($user)->postJson(route('campaigns.preview'), $this->payload())->assertForbidden();

        $this->assertSame(0, Campaign::count());
    }

    public function test_without_view_permission_the_whole_section_is_hidden(): void
    {
        $user = $this->user(UserRole::Manager, ['campaigns' => []]);

        $this->actingAs($user)->get(route('campaigns.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('campaigns.index'));
    }

    public function test_cancelling_needs_edit_permission(): void
    {
        $user = $this->user(UserRole::BusinessDevelopment, ['campaigns' => ['view', 'create']]);
        $campaign = Campaign::factory()->create(['created_by' => $user->id, 'status' => CampaignStatus::Pending]);

        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertOk()->assertDontSee('Cancel Campaign');
        $this->actingAs($user)->post(route('campaigns.cancel', $campaign))->assertForbidden();

        $this->assertSame(CampaignStatus::Pending, $campaign->fresh()->status);
    }

    public function test_only_pending_or_sending_campaigns_can_be_cancelled_and_only_by_who_can_see_them(): void
    {
        $owner = $this->user(UserRole::BusinessDevelopment);
        $colleague = $this->user(UserRole::BusinessDevelopment);
        $manager = $this->user(UserRole::Manager);
        $completed = Campaign::factory()->create(['created_by' => $owner->id, 'status' => CampaignStatus::Completed]);
        $pending = Campaign::factory()->create(['created_by' => $owner->id, 'status' => CampaignStatus::Pending]);
        $pending->recipients()->create(['address' => 'a@example.com', 'status' => CampaignRecipientStatus::Pending]);

        $this->actingAs($owner)->post(route('campaigns.cancel', $completed))->assertForbidden();
        $this->actingAs($colleague)->post(route('campaigns.cancel', $pending))->assertForbidden();
        $this->actingAs($owner)->get(route('campaigns.show', $pending))->assertSee('Cancel Campaign');
        $this->actingAs($manager)->post(route('campaigns.cancel', $pending))->assertRedirect();

        $this->assertSame(CampaignStatus::Cancelled, $pending->fresh()->status);
    }

    // --- Campaign Setup: Super Admin only ---------------------------------

    public function test_only_super_admin_can_open_or_change_campaign_setup(): void
    {
        foreach ([UserRole::Manager, UserRole::BusinessDevelopment, UserRole::CustomerSuccess, UserRole::Finance] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('campaign-setup.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('campaign-setup.update'), ['sms_method' => 'POST', 'sms_format' => 'form', 'sms_auth_mode' => 'param'])->assertForbidden();
            $this->actingAs($user)->put(route('campaign-setup.update-email'), ['campaign_email_mode' => 'system', 'campaign_batch_size' => 100, 'campaign_emails_per_minute' => 20, 'campaign_batch_pause_minutes' => 15])->assertForbidden();
            $this->actingAs($user)->get(route('campaign-setup.domain-check'))->assertForbidden();
            $this->actingAs($user)->post(route('campaign-setup.test-email'), ['test_email' => 'a@example.com'])->assertForbidden();
            $this->actingAs($user)->post(route('campaign-setup.test-sms'), ['test_phone' => '9800000000'])->assertForbidden();
            $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('campaign-setup.edit'));
        }

        $admin = $this->user(UserRole::SuperAdmin);
        $this->actingAs($admin)->get(route('campaign-setup.edit'))->assertOk()->assertSee('Save Email Setup')->assertSee('Signature');
        $this->actingAs($admin)->get(route('campaign-setup.edit', ['tab' => 'sms']))->assertOk()->assertSee('Delivery-report');
        $this->actingAs($admin)->get(route('dashboard'))->assertSee(route('campaign-setup.edit'));
    }

    public function test_setup_saves_gateway_settings_and_keeps_the_key_encrypted(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);
        $settings = app(CampaignSettings::class);
        $fields = [
            'sms_endpoint' => 'https://api.sparrowsms.com/v2/sms/', 'sms_method' => 'POST', 'sms_format' => 'form',
            'sms_auth_mode' => 'param', 'sms_auth_name' => 'token', 'sms_to_param' => 'to', 'sms_message_param' => 'text',
        ];

        $this->actingAs($admin)->put(route('campaign-setup.update'), [...$fields, 'sms_api_key' => 'my-secret-key'])->assertSessionHasNoErrors();

        $this->assertSame('https://api.sparrowsms.com/v2/sms/', $settings->get('sms_endpoint'));
        $this->assertSame('my-secret-key', $settings->smsApiKey());
        $this->assertDatabaseMissing('settings', ['value' => 'my-secret-key']);
        $this->actingAs($admin)->get(route('campaign-setup.edit', ['tab' => 'sms']))->assertDontSee('my-secret-key')->assertSee('saved — leave blank to keep');

        // Blank key keeps the saved one; "Remove saved key" clears it.
        $this->actingAs($admin)->put(route('campaign-setup.update'), [...$fields, 'sms_api_key' => '']);
        $this->assertSame('my-secret-key', $settings->smsApiKey());

        $this->actingAs($admin)->put(route('campaign-setup.update'), [...$fields, 'clear_sms_api_key' => '1']);
        $this->assertNull($settings->smsApiKey());
    }

    public function test_setup_requires_parameter_names_once_an_endpoint_is_given(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);

        $this->actingAs($admin)->put(route('campaign-setup.update'), [
            'sms_endpoint' => 'https://sms.test/send', 'sms_method' => 'POST', 'sms_format' => 'form', 'sms_auth_mode' => 'param',
            'sms_country_prefix' => 'abc',
        ])->assertSessionHasErrors(['sms_to_param', 'sms_message_param', 'sms_country_prefix']);
    }

    public function test_setup_test_buttons_send_through_the_saved_settings(): void
    {
        Mail::fake();
        Http::fake(['sms.test/*' => Http::response('queued', 200)]);
        $admin = $this->user(UserRole::SuperAdmin);
        $this->actingAs($admin)->put(route('campaign-setup.update'), [
            'sms_endpoint' => 'https://sms.test/send', 'sms_method' => 'GET', 'sms_format' => 'form', 'sms_auth_mode' => 'bearer',
            'sms_to_param' => 'to', 'sms_message_param' => 'text', 'sms_api_key' => 'k',
        ]);

        $this->actingAs($admin)->post(route('campaign-setup.test-email'), ['test_email' => 'me@example.com'])->assertSessionHas('success');
        Mail::assertSent(\App\Mail\CampaignMail::class, fn ($mail) => $mail->hasTo('me@example.com'));

        $this->actingAs($admin)->post(route('campaign-setup.test-sms'), ['test_phone' => '+977 9800000000'])->assertSessionHas('success');
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request['to'] === '9800000000' && $request->hasHeader('Authorization', 'Bearer k'));

        $this->actingAs($admin)->post(route('campaign-setup.test-sms'), ['test_phone' => 'abc'])->assertSessionHasErrors('test_phone');
    }

    public function test_the_permissions_screen_lists_campaigns(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);
        $member = $this->user(UserRole::BusinessDevelopment);

        $this->actingAs($admin)->get(route('users.permissions.edit', $member))->assertOk()->assertSee('Campaigns');
    }
}
