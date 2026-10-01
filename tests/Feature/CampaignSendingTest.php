<?php

namespace Tests\Feature;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignMessage;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Lead;
use App\Models\User;
use App\Services\CampaignService;
use App\Support\CampaignSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignSendingTest extends TestCase
{
    use RefreshDatabase;

    private function configureSms(array $overrides = []): CampaignSettings
    {
        $settings = app(CampaignSettings::class);

        foreach ([
            'sms_endpoint' => 'https://sms.test/send',
            'sms_method' => 'POST',
            'sms_format' => 'form',
            'sms_auth_mode' => 'param',
            'sms_auth_name' => 'token',
            'sms_to_param' => 'to',
            'sms_message_param' => 'text',
            'sms_sender_param' => 'from',
            'sms_sender_id' => 'HAJIR',
            'sms_success_path' => 'response_code',
            'sms_success_value' => '200',
            'sms_message_id_path' => 'message_id',
            ...$overrides,
        ] as $key => $value) {
            $settings->set($key, $value);
        }

        $settings->setSmsApiKey('secret-token');

        return $settings;
    }

    private function emailCampaignPayload(array $overrides = []): array
    {
        return [
            'name' => 'Dashain offer',
            'channel' => 'email',
            'subject' => 'Offer for {{company_name}}',
            'message' => "Hi {{name}},\nHappy Dashain!",
            'audience' => 'all_leads',
            ...$overrides,
        ];
    }

    public function test_an_email_campaign_is_saved_and_queued_throttled_per_minute(): void
    {
        Queue::fake();
        config(['campaigns.emails_per_minute' => 2]);
        $user = User::factory()->superAdmin()->create();
        Lead::factory()->count(3)->sequence(fn ($s) => ['email' => "lead{$s->index}@example.com"])->create();

        $this->actingAs($user)->post(route('campaigns.store'), $this->emailCampaignPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $campaign = Campaign::firstOrFail();
        $this->assertSame(CampaignStatus::Sending, $campaign->status);
        $this->assertSame(3, $campaign->recipient_count);
        $this->assertSame(3, $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->count());
        $this->assertSame(3, $campaign->recipients()->whereNotNull('tracking_token')->count());

        Queue::assertPushedOn('campaigns', SendCampaignMessage::class);
        Queue::assertPushed(SendCampaignMessage::class, 3);
        $delays = collect(Queue::pushed(SendCampaignMessage::class))->map(fn ($job) => now()->diffInMinutes($job->delay, true))->map(fn ($m) => (int) round($m))->sort()->values()->all();
        $this->assertSame([0, 0, 1], $delays);
    }

    public function test_sending_an_email_personalizes_it_and_marks_the_campaign_completed(): void
    {
        Mail::fake();
        $user = User::factory()->superAdmin()->create();
        Lead::factory()->create(['company_name' => 'Acme', 'contact_person' => 'Ram', 'email' => 'ram@acme.test']);

        $this->actingAs($user)->post(route('campaigns.store'), $this->emailCampaignPayload());

        $recipient = CampaignRecipient::firstOrFail();
        $this->assertSame(CampaignRecipientStatus::Sent, $recipient->status);
        $this->assertNotNull($recipient->sent_at);
        $this->assertSame(CampaignStatus::Completed, $recipient->campaign->fresh()->status);

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail) use ($recipient) {
            return $mail->hasTo('ram@acme.test')
                && $mail->renderedSubject === 'Offer for Acme'
                && $mail->renderedBody === "Hi Ram,\nHappy Dashain!"
                && $mail->trackingUrl === route('campaigns.track-open', $recipient->tracking_token);
        });
    }

    public function test_the_email_contains_the_tracking_image(): void
    {
        $html = (new CampaignMail('Subject', 'Body', 'https://crm.test/track/email/abc'))->render();

        $this->assertStringContainsString('src="https://crm.test/track/email/abc"', $html);
    }

    public function test_opening_the_email_marks_it_delivered_without_starting_a_session(): void
    {
        $campaign = Campaign::factory()->create();
        $recipient = $campaign->recipients()->create(['address' => 'a@example.com', 'status' => CampaignRecipientStatus::Sent, 'tracking_token' => 'tok123']);

        $response = $this->get(route('campaigns.track-open', 'tok123'));

        $response->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertEmpty($response->headers->getCookies(), 'the tracking pixel must not set a session cookie');
        $this->assertSame(CampaignRecipientStatus::Delivered, $recipient->fresh()->status);
        $this->assertNotNull($recipient->fresh()->delivered_at);

        $this->get(route('campaigns.track-open', 'unknown-token'))->assertOk()->assertHeader('Content-Type', 'image/gif');
    }

    public function test_an_email_failure_is_recorded_with_the_reason(): void
    {
        $user = User::factory()->superAdmin()->create();
        config(['campaigns.mail' => ['host' => '127.0.0.1', 'port' => 1, 'username' => 'campaigns@example.com', 'password' => 'x', 'encryption' => 'none', 'from_address' => 'campaigns@example.com', 'from_name' => null, 'reply_to' => null]]);

        $this->actingAs($user)->post(route('campaigns.store'), $this->emailCampaignPayload([
            'audience' => 'none',
            'extra_contacts' => 'someone@example.com',
        ]));

        $recipient = CampaignRecipient::firstOrFail();
        $this->assertSame(CampaignRecipientStatus::Failed, $recipient->status);
        $this->assertStringContainsString('127.0.0.1', $recipient->error, 'it went through the CAMPAIGN_MAIL_* login, not the default mailer');
        $this->assertSame(CampaignStatus::Completed, $recipient->campaign->fresh()->status);
    }

    public function test_an_sms_campaign_is_sent_through_the_configured_gateway(): void
    {
        $this->configureSms();
        Http::fake(['sms.test/*' => Http::response(['response_code' => 200, 'message_id' => 'gw-1'])]);
        $user = User::factory()->superAdmin()->create();
        Lead::factory()->create(['contact_person' => 'Sita', 'phone' => '+977 9800000001']);

        $this->actingAs($user)->post(route('campaigns.store'), [
            'name' => 'SMS blast',
            'channel' => 'sms',
            'message' => 'Namaste {{name}}',
            'audience' => 'all_leads',
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://sms.test/send'
            && $request['to'] === '9800000001'
            && $request['text'] === 'Namaste Sita'
            && $request['token'] === 'secret-token'
            && $request['from'] === 'HAJIR');

        $recipient = CampaignRecipient::firstOrFail();
        $this->assertSame(CampaignRecipientStatus::Sent, $recipient->status);
        $this->assertSame('gw-1', $recipient->provider_message_id);
    }

    public function test_sms_api_key_can_be_sent_as_a_header_and_numbers_get_a_country_prefix(): void
    {
        $this->configureSms(['sms_auth_mode' => 'header', 'sms_auth_name' => 'X-Api-Key', 'sms_country_prefix' => '977', 'sms_format' => 'json']);
        Http::fake(['sms.test/*' => Http::response(['response_code' => 200])]);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post(route('campaigns.store'), [
            'name' => 'SMS', 'channel' => 'sms', 'message' => 'Hi', 'audience' => 'none', 'extra_contacts' => '9800000001',
        ]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Api-Key', 'secret-token')
            && $request->isJson()
            && $request['to'] === '9779800000001'
            && ! isset($request['token']));
    }

    public function test_a_gateway_rejection_marks_the_sms_failed_with_the_response(): void
    {
        $this->configureSms();
        Http::fake(['sms.test/*' => Http::response(['response_code' => 1002, 'response' => 'Invalid token'])]);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post(route('campaigns.store'), [
            'name' => 'SMS', 'channel' => 'sms', 'message' => 'Hi', 'audience' => 'none', 'extra_contacts' => '9800000001',
        ]);

        $recipient = CampaignRecipient::firstOrFail();
        $this->assertSame(CampaignRecipientStatus::Failed, $recipient->status);
        $this->assertStringContainsString('Invalid token', $recipient->error);
    }

    public function test_sms_fails_clearly_when_the_gateway_is_not_set_up(): void
    {
        Http::fake();
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post(route('campaigns.store'), [
            'name' => 'SMS', 'channel' => 'sms', 'message' => 'Hi', 'audience' => 'none', 'extra_contacts' => '9800000001',
        ]);

        $this->assertStringContainsString('not set up', CampaignRecipient::firstOrFail()->error);
        Http::assertNothingSent();
    }

    public function test_delivery_reports_mark_sms_delivered_or_failed(): void
    {
        $settings = $this->configureSms(['sms_dlr_id_param' => 'msgid', 'sms_dlr_status_param' => 'state', 'sms_dlr_delivered_values' => 'DELIVRD', 'sms_dlr_failed_values' => 'UNDELIV']);
        $campaign = Campaign::factory()->sms()->create();
        $delivered = $campaign->recipients()->create(['address' => '9800000001', 'status' => CampaignRecipientStatus::Sent, 'provider_message_id' => 'm-1']);
        $failed = $campaign->recipients()->create(['address' => '9800000002', 'status' => CampaignRecipientStatus::Sent, 'provider_message_id' => 'm-2']);
        $url = route('webhooks.sms-delivery', $settings->dlrSecret());

        $this->post($url, ['msgid' => 'm-1', 'state' => 'DELIVRD'])->assertOk();
        $this->get($url.'?msgid=m-2&state=undeliv')->assertOk();

        $this->assertSame(CampaignRecipientStatus::Delivered, $delivered->fresh()->status);
        $this->assertSame(CampaignRecipientStatus::Failed, $failed->fresh()->status);
        $this->assertStringContainsString('undeliv', $failed->fresh()->error);
    }

    public function test_delivery_reports_with_a_wrong_secret_are_rejected(): void
    {
        $this->configureSms();
        $campaign = Campaign::factory()->sms()->create();
        $recipient = $campaign->recipients()->create(['address' => '9800000001', 'status' => CampaignRecipientStatus::Sent, 'provider_message_id' => 'm-1']);

        $this->post(route('webhooks.sms-delivery', 'wrong-secret'), ['message_id' => 'm-1', 'status' => 'delivered'])->assertNotFound();

        $this->assertSame(CampaignRecipientStatus::Sent, $recipient->fresh()->status);
    }

    public function test_a_scheduled_campaign_stays_pending_until_its_time(): void
    {
        Queue::fake();
        $user = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'a@example.com']);

        $this->actingAs($user)->post(route('campaigns.store'), $this->emailCampaignPayload([
            'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();

        $campaign = Campaign::firstOrFail();
        $this->assertSame(CampaignStatus::Pending, $campaign->status);
        Queue::assertNothingPushed();

        $this->artisan('campaigns:dispatch-due');
        Queue::assertNothingPushed();

        $this->travel(61)->minutes();
        $this->artisan('campaigns:dispatch-due');

        Queue::assertPushed(SendCampaignMessage::class, 1);
        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
    }

    public function test_a_campaign_is_never_queued_twice(): void
    {
        Queue::fake();
        $user = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'a@example.com']);

        $this->actingAs($user)->post(route('campaigns.store'), $this->emailCampaignPayload());
        $campaign = Campaign::firstOrFail();

        app(CampaignService::class)->dispatch($campaign);
        app(CampaignService::class)->dispatchDue();

        Queue::assertPushed(SendCampaignMessage::class, 1);
    }

    public function test_cancelling_stops_unsent_messages(): void
    {
        Queue::fake();
        Mail::fake();
        $user = User::factory()->superAdmin()->create();
        Lead::factory()->count(2)->sequence(fn ($s) => ['email' => "l{$s->index}@example.com"])->create();

        $this->actingAs($user)->post(route('campaigns.store'), $this->emailCampaignPayload());
        $campaign = Campaign::firstOrFail();

        $this->actingAs($user)->post(route('campaigns.cancel', $campaign))->assertSessionHas('success');

        $this->assertSame(CampaignStatus::Cancelled, $campaign->fresh()->status);
        $this->assertSame(2, $campaign->recipients()->where('status', CampaignRecipientStatus::Cancelled)->count());

        // Jobs already queued run later and must skip the cancelled rows.
        collect(Queue::pushed(SendCampaignMessage::class))->each(fn ($job) => app()->call([$job, 'handle']));
        Mail::assertNothingSent();

        $this->actingAs($user)->post(route('campaigns.cancel', $campaign))->assertForbidden();
    }

    public function test_a_campaign_with_nobody_to_send_to_is_rejected(): void
    {
        $user = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => null]);

        $this->actingAs($user)->post(route('campaigns.store'), $this->emailCampaignPayload())
            ->assertSessionHasErrors('recipients');

        $this->assertSame(0, Campaign::count());
    }

    public function test_campaign_validation(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post(route('campaigns.store'), ['channel' => 'email', 'audience' => 'all_leads'])
            ->assertSessionHasErrors(['name', 'subject', 'message']);

        $this->actingAs($user)->post(route('campaigns.store'), [
            'name' => 'x', 'channel' => 'sms', 'audience' => 'all_leads',
            'message' => str_repeat('a', 919),
            'scheduled_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors(['message', 'scheduled_at'])->assertSessionDoesntHaveErrors('subject');
    }

    public function test_campaign_pages_render_with_statuses_and_left_out_entries(): void
    {
        $user = User::factory()->superAdmin()->create();
        $campaign = Campaign::factory()->create(['created_by' => $user->id, 'recipient_count' => 2, 'skipped' => [['value' => 'dup@example.com', 'reason' => 'Duplicate — already included', 'type' => 'duplicate']]]);
        $campaign->recipients()->create(['address' => 'a@example.com', 'status' => CampaignRecipientStatus::Delivered]);
        $campaign->recipients()->create(['address' => 'b@example.com', 'status' => CampaignRecipientStatus::Failed, 'error' => 'Mailbox full']);

        $this->actingAs($user)->get(route('campaigns.index'))->assertOk()->assertSee($campaign->name)->assertSee('1 opened')->assertSee('1 failed');
        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('dup@example.com')->assertSee('Mailbox full')->assertSee('a@example.com');
        $this->actingAs($user)->get(route('campaigns.show', [$campaign, 'status' => 'failed']))->assertOk()
            ->assertSee('b@example.com')->assertDontSee('a@example.com');
        $this->actingAs($user)->get(route('campaigns.create'))->assertOk();
    }
}
