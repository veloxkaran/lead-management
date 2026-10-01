<?php

namespace Tests\Feature;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Jobs\SendCampaignMessage;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignUnsubscribe;
use App\Models\User;
use App\Services\CampaignService;
use App\Support\CampaignSettings;
use App\Support\SenderDomainCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignBatchingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin]);
    }

    private function speed(int $batchSize, int $perMinute, int $pause): void
    {
        $settings = app(CampaignSettings::class);
        $settings->set('campaign_batch_size', (string) $batchSize);
        $settings->set('campaign_emails_per_minute', (string) $perMinute);
        $settings->set('campaign_batch_pause_minutes', (string) $pause);
    }

    /** A CAMPAIGN_MAIL_* login, as .env would give it. */
    private function campaignMail(array $overrides = []): void
    {
        config(['campaigns.mail' => [
            'host' => '127.0.0.1', 'port' => 1, 'username' => 'news@acme.test', 'password' => 'env-secret',
            'encryption' => 'none', 'from_address' => 'news@acme.test', 'from_name' => 'Acme News', 'reply_to' => null,
            ...$overrides,
        ]]);
    }

    private function contacts(int $count): string
    {
        return collect(range(1, $count))->map(fn ($i) => "person{$i}@example.com")->implode("\n");
    }

    private function createCampaign(User $user, int $contacts, array $overrides = []): Campaign
    {
        $this->actingAs($user)->postCampaign([
            'name' => 'Big send',
            'channel' => 'email',
            'subject' => 'Hello {{name}}',
            'message' => 'Hi there',
            'audience' => 'none',
            'extra_contacts' => $this->contacts($contacts),
            ...$overrides,
        ])->assertSessionHasNoErrors();

        return Campaign::latest('id')->firstOrFail();
    }

    /** Runs the queued jobs (Queue::fake) as the worker would, then forgets them. */
    private function runQueuedJobs(): void
    {
        $jobs = Queue::pushed(SendCampaignMessage::class);
        Queue::fake();

        foreach ($jobs as $job) {
            app()->call([$job, 'handle']);
        }
    }

    public function test_2000_emails_are_split_into_batches_of_100_and_only_the_first_is_queued(): void
    {
        Queue::fake();
        $this->speed(100, 20, 15);

        $campaign = $this->createCampaign($this->admin(), 2000);

        $this->assertSame(2000, $campaign->recipient_count);
        $this->assertSame(20, $campaign->batch_count);
        $this->assertSame(['batch_size' => 100, 'per_minute' => 20, 'pause_minutes' => 15, 'include_signature' => false, 'track_opens' => true], $campaign->send_options);
        $this->assertSame(array_fill(1, 20, 100), $campaign->recipients()->selectRaw('batch, COUNT(*) as n')->groupBy('batch')->pluck('n', 'batch')->map(fn ($n) => (int) $n)->all());

        // Only batch 1 goes into the queue, spread at 20 a minute over 5 minutes.
        Queue::assertPushed(SendCampaignMessage::class, 100);
        $this->assertSame(100, $campaign->recipients()->whereNotNull('queued_at')->where('batch', 1)->count());
        $this->assertSame(0, $campaign->recipients()->whereNotNull('queued_at')->where('batch', '>', 1)->count());
        $delays = collect(Queue::pushed(SendCampaignMessage::class))->map(fn ($job) => (int) round(now()->diffInSeconds($job->delay, true)));
        $this->assertSame(0, $delays->min());
        $this->assertSame(297, $delays->max());

        $campaign->refresh();
        $this->assertSame(1, $campaign->current_batch);
        // 5 minutes of sending + 15 minutes pause.
        $this->assertEqualsWithDelta(now()->addMinutes(20)->timestamp, $campaign->next_batch_at->timestamp, 2);
    }

    public function test_the_next_batch_waits_for_its_time_and_for_the_previous_batch_to_finish(): void
    {
        Queue::fake();
        Mail::fake();
        $this->speed(2, 60, 10);
        $campaign = $this->createCampaign($this->admin(), 5);
        $this->assertSame(3, $campaign->batch_count);
        Queue::assertPushed(SendCampaignMessage::class, 2);

        // Time has come, but batch 1 hasn't been sent yet (worker down) — nothing new is queued.
        $this->travel(30)->minutes();
        Queue::fake();
        app(CampaignService::class)->dispatchDue();
        Queue::assertNothingPushed();

        // Worker catches up: batch 1 goes, and the full pause follows it.
        Queue::fake();
        foreach ($campaign->recipients()->where('batch', 1)->pluck('id') as $id) {
            app()->call([new SendCampaignMessage($id), 'handle']);
        }
        $this->assertSame(2, $campaign->recipients()->where('status', CampaignRecipientStatus::Sent)->count());
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $campaign->fresh()->next_batch_at->timestamp, 2);

        app(CampaignService::class)->dispatchDue();
        Queue::assertNothingPushed();

        $this->travel(11)->minutes();
        app(CampaignService::class)->dispatchDue();
        Queue::assertPushed(SendCampaignMessage::class, 2);
        $this->assertSame(2, $campaign->fresh()->current_batch);

        $this->runQueuedJobs();
        $this->travel(15)->minutes();
        app(CampaignService::class)->dispatchDue();
        Queue::assertPushed(SendCampaignMessage::class, 1);
        $this->runQueuedJobs();

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Completed, $campaign->status);
        $this->assertNull($campaign->next_batch_at);
        $this->assertSame(5, $campaign->recipients()->where('status', CampaignRecipientStatus::Sent)->count());
        Mail::assertSent(CampaignMail::class, 5);
    }

    public function test_with_a_sync_queue_every_batch_eventually_goes_out_through_the_scheduler(): void
    {
        Mail::fake();
        $this->speed(2, 60, 5);
        $campaign = $this->createCampaign($this->admin(), 5);

        $this->assertSame(2, $campaign->recipients()->where('status', CampaignRecipientStatus::Sent)->count());

        foreach (range(1, 2) as $ignored) {
            $this->travel(10)->minutes();
            $this->artisan('campaigns:dispatch-due')->assertSuccessful();
        }

        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
        Mail::assertSent(CampaignMail::class, 5);
    }

    public function test_pausing_puts_queued_messages_back_and_resuming_sends_them(): void
    {
        Queue::fake();
        Mail::fake();
        $user = $this->admin();
        $this->speed(3, 60, 5);
        $campaign = $this->createCampaign($user, 3);

        $this->actingAs($user)->post(route('campaigns.pause', $campaign))->assertSessionHas('success');
        $this->assertSame(CampaignStatus::Paused, $campaign->fresh()->status);

        // The worker gets to the already-queued jobs while paused: nothing is sent.
        $this->runQueuedJobs();
        Mail::assertNothingSent();
        $this->assertSame(3, $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->whereNull('queued_at')->count());

        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Resume');

        $this->actingAs($user)->post(route('campaigns.resume', $campaign))->assertSessionHas('success');
        Queue::assertPushed(SendCampaignMessage::class, 3);
        $this->runQueuedJobs();

        Mail::assertSent(CampaignMail::class, 3);
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);

        $this->actingAs($user)->post(route('campaigns.pause', $campaign))->assertForbidden();
    }

    public function test_retry_resends_only_messages_that_never_left(): void
    {
        Mail::fake();
        $user = $this->admin();
        $campaign = Campaign::factory()->create(['created_by' => $user->id, 'status' => CampaignStatus::Completed, 'recipient_count' => 2, 'batch_count' => 1, 'completed_at' => now()]);
        $refused = $campaign->recipients()->create(['batch' => 1, 'address' => 'refused@example.com', 'status' => CampaignRecipientStatus::Failed, 'error' => '550 mailbox unavailable', 'tracking_token' => 'tok-a']);
        $bounced = $campaign->recipients()->create(['batch' => 1, 'address' => 'bounced@example.com', 'status' => CampaignRecipientStatus::Failed, 'error' => 'Gateway delivery report: failed', 'sent_at' => now(), 'tracking_token' => 'tok-b']);

        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertSee('Retry 1 failed');
        $this->actingAs($user)->post(route('campaigns.retry-failed', $campaign))->assertSessionHas('success');

        $this->assertSame(CampaignRecipientStatus::Sent, $refused->fresh()->status);
        $this->assertNull($refused->fresh()->error);
        $this->assertSame(CampaignRecipientStatus::Failed, $bounced->fresh()->status);
        Mail::assertSent(CampaignMail::class, fn ($mail) => $mail->hasTo('refused@example.com'));
        Mail::assertNotSent(CampaignMail::class, fn ($mail) => $mail->hasTo('bounced@example.com'));
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);

        $cancelled = Campaign::factory()->create(['created_by' => $user->id, 'status' => CampaignStatus::Cancelled]);
        $this->actingAs($user)->post(route('campaigns.retry-failed', $cancelled))->assertForbidden();
    }

    public function test_campaign_email_carries_signature_footer_unsubscribe_and_a_text_part(): void
    {
        Mail::fake();
        $settings = app(CampaignSettings::class);
        $settings->set('campaign_signature', '<p><strong>Ram Sharma</strong><br>Acme Pvt. Ltd.</p>');
        $settings->set('campaign_footer', "Acme Pvt. Ltd.\nPutalisadak, Kathmandu");

        $this->createCampaign($this->admin(), 1);
        $recipient = CampaignRecipient::firstOrFail();

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail) use ($recipient) {
            $unsubscribe = route('campaigns.unsubscribe', $recipient->tracking_token);

            $mail->assertSeeInHtml('Ram Sharma', false)
                ->assertSeeInHtml('Putalisadak, Kathmandu')
                ->assertSeeInHtml($unsubscribe)
                ->assertSeeInText('Ram Sharma')
                ->assertSeeInText("Unsubscribe: {$unsubscribe}")
                ->assertDontSeeInText('<strong>');

            return $mail->headers()->text['List-Unsubscribe'] === "<{$unsubscribe}>"
                && $mail->headers()->text['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });
    }

    public function test_from_and_reply_to_come_from_the_campaign_mail_login(): void
    {
        $this->campaignMail(['reply_to' => 'sales@acme.test']);
        $mail = app(\App\Services\CampaignMailer::class)->prepare(new CampaignMail('Subject', 'Body'));

        // Set by CampaignMailer at send time, so on the properties rather than the envelope.
        $this->assertEquals([['address' => 'news@acme.test', 'name' => 'Acme News']], $mail->from);
        $this->assertEquals([['address' => 'sales@acme.test', 'name' => null]], $mail->replyTo);

        // Without a From address, the login's own address is used.
        $this->campaignMail(['from_address' => null, 'username' => 'login@acme.test']);
        $this->assertSame('login@acme.test', app(\App\Services\CampaignMailer::class)->from()[0]);
    }

    public function test_the_signature_can_be_left_off_and_open_tracking_turned_off(): void
    {
        Mail::fake();
        $settings = app(CampaignSettings::class);
        $settings->set('campaign_signature', '<p>Ram Sharma</p>');
        $settings->set('campaign_track_opens', '0');

        $campaign = $this->createCampaign($this->admin(), 1, ['include_signature' => '0']);

        $this->assertFalse($campaign->send_options['include_signature']);
        $this->assertFalse($campaign->send_options['track_opens']);
        Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->signatureHtml === null && $mail->trackingUrl === null && $mail->unsubscribeUrl !== null);
    }

    public function test_unsubscribe_asks_first_then_suppresses_the_address_in_future_campaigns(): void
    {
        Mail::fake();
        $campaign = Campaign::factory()->create();
        $recipient = $campaign->recipients()->create(['address' => 'ram@example.com', 'status' => CampaignRecipientStatus::Sent, 'tracking_token' => 'tok-unsub']);

        // GET only asks — link scanners must not unsubscribe anyone.
        $this->get(route('campaigns.unsubscribe', 'tok-unsub'))->assertOk()->assertSee('Unsubscribe?')->assertSee('ram@example.com');
        $this->assertSame(0, CampaignUnsubscribe::count());

        // Mail-app one-click: a bare POST, no session or CSRF token.
        $response = $this->post(route('campaigns.unsubscribe.confirm', 'tok-unsub'), ['List-Unsubscribe' => 'One-Click']);
        $response->assertOk()->assertSee("You're unsubscribed", false);
        $this->assertEmpty($response->headers->getCookies());
        $this->assertNotNull($recipient->fresh()->unsubscribed_at);
        $this->assertDatabaseHas('campaign_unsubscribes', ['address' => 'ram@example.com', 'channel' => 'email', 'reason' => 'One-click unsubscribe (mail app)']);

        // Twice is harmless.
        $this->post(route('campaigns.unsubscribe.confirm', 'tok-unsub'))->assertOk();
        $this->assertSame(1, CampaignUnsubscribe::count());

        $this->get(route('campaigns.unsubscribe', 'not-a-token'))->assertOk()->assertSee('Link not recognised');

        // The next campaign leaves them out and says why.
        $user = $this->admin();
        $this->actingAs($user)->postJson(route('campaigns.preview'), ['channel' => 'email', 'audience' => 'none', 'extra_contacts' => "RAM@example.com\nsita@example.com"])
            ->assertOk()->assertJson(['total' => 1, 'unsubscribed' => 1]);

        $next = $this->createCampaign($user, 0, ['extra_contacts' => "ram@example.com\nsita@example.com"]);
        $this->assertSame(['sita@example.com'], $next->recipients()->pluck('address')->all());
        $this->assertSame('unsubscribed', $next->skipped[0]['type']);
    }

    public function test_someone_who_unsubscribes_before_their_batch_is_skipped(): void
    {
        Queue::fake();
        Mail::fake();
        $this->speed(1, 60, 0);
        $campaign = $this->createCampaign($this->admin(), 2);
        $second = $campaign->recipients()->where('batch', 2)->firstOrFail();

        CampaignUnsubscribe::create(['channel' => 'email', 'address' => $second->address]);
        $this->runQueuedJobs();
        $this->travel(2)->minutes();
        app(CampaignService::class)->dispatchDue();
        $this->runQueuedJobs();

        $this->assertSame(CampaignRecipientStatus::Cancelled, $second->fresh()->status);
        $this->assertStringContainsString('Unsubscribed', $second->fresh()->error);
        Mail::assertSent(CampaignMail::class, 1);
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
    }

    public function test_email_setup_saves_signature_footer_and_speed(): void
    {
        $admin = $this->admin();
        $settings = app(CampaignSettings::class);

        $this->actingAs($admin)->put(route('campaign-setup.update-email'), [
            'campaign_signature' => '<p>Ram<script>alert(1)</script></p>',
            'campaign_footer' => 'Acme, Kathmandu',
            'campaign_track_opens' => '0',
            'campaign_batch_size' => 50,
            'campaign_emails_per_minute' => 10,
            'campaign_batch_pause_minutes' => 30,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertStringNotContainsString('<script', $settings->signature());
        $this->assertStringContainsString('Ram', $settings->signature());
        $this->assertSame('Acme, Kathmandu', $settings->get('campaign_footer'));
        $this->assertFalse($settings->tracksOpens());
        $this->assertSame(['batch_size' => 50, 'per_minute' => 10, 'pause_minutes' => 30], array_intersect_key($settings->sendOptions(\App\Enums\CampaignChannel::Email), array_flip(['batch_size', 'per_minute', 'pause_minutes'])));

        // Speed limits are enforced.
        $this->actingAs($admin)->put(route('campaign-setup.update-email'), [
            'campaign_batch_size' => 5000, 'campaign_emails_per_minute' => 500, 'campaign_batch_pause_minutes' => 15,
        ])->assertSessionHasErrors(['campaign_batch_size', 'campaign_emails_per_minute']);

        // The sender can't be set from the page any more.
        $this->actingAs($admin)->put(route('campaign-setup.update-email'), [
            'campaign_smtp_host' => 'evil.test', 'campaign_batch_size' => 100, 'campaign_emails_per_minute' => 20, 'campaign_batch_pause_minutes' => 15,
        ])->assertSessionHasNoErrors();
        $this->assertNull($settings->get('campaign_smtp_host'));

        // With no CAMPAIGN_MAIL_HOST the page says email falls back to MAIL_*.
        $this->actingAs($admin)->get(route('campaign-setup.edit'))->assertOk()
            ->assertSee('No campaign login set.')
            ->assertDontSee('name="campaign_email_mode"', false)
            ->assertDontSee('name="campaign_smtp_password"', false);
    }

    public function test_the_send_log_filters_by_batch_and_search_and_exports_csv(): void
    {
        Queue::fake();
        $user = $this->admin();
        $this->speed(2, 60, 5);
        $campaign = $this->createCampaign($user, 5);

        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Batch <strong>1</strong> of 3', false)
            ->assertSee('Next batch')
            ->assertSee('person5@example.com');

        $this->actingAs($user)->get(route('campaigns.show', [$campaign, 'batch' => 3]))->assertOk()
            ->assertSee('person5@example.com')->assertDontSee('person1@example.com');

        $this->actingAs($user)->get(route('campaigns.show', [$campaign, 'search' => 'person2@']))->assertOk()
            ->assertSee('person2@example.com')->assertDontSee('person3@example.com');

        $csv = $this->actingAs($user)->get(route('campaigns.export', $campaign))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertCount(6, $lines);
        $this->assertStringStartsWith('Batch,Email,Name,Company,Source,Status,Error,"Queued at","Sent at","Opened at","Unsubscribed at"', $lines[0]);
        $this->assertStringContainsString('3,person5@example.com', $csv);

        $noView = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $noView->forceFill(['permissions' => ['campaigns' => []]])->save();
        $this->actingAs($noView)->get(route('campaigns.export', $campaign))->assertForbidden();
    }

    public function test_messages_stuck_in_the_queue_are_flagged(): void
    {
        Queue::fake();
        $user = $this->admin();
        $campaign = $this->createCampaign($user, 2);

        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertDontSee('waiting in the queue longer');

        $this->travel(30)->minutes();
        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertSee('2 message(s) have been waiting in the queue longer');
    }

    public function test_the_domain_check_reports_spf_dkim_and_dmarc(): void
    {
        $this->campaignMail(['from_address' => 'news@acme.test']);
        $this->app->instance(SenderDomainCheck::class, new class extends SenderDomainCheck
        {
            protected function txt(string $host): array
            {
                return match ($host) {
                    'acme.test' => ['v=spf1 include:_spf.host.test ~all', 'google-site-verification=x'],
                    'default._domainkey.acme.test' => ['v=DKIM1; k=rsa; p=MIIB'],
                    default => [],
                };
            }

            protected function hasMx(string $domain): bool
            {
                return true;
            }
        });

        $result = $this->actingAs($this->admin())->getJson(route('campaign-setup.domain-check'))->assertOk()->json();

        $this->assertSame('acme.test', $result['domain']);
        $this->assertSame(['spf' => 'ok', 'dkim' => 'ok', 'dmarc' => 'missing', 'mx' => 'ok'], collect($result['checks'])->pluck('status', 'key')->all());
    }

    public function test_a_refused_login_points_out_a_username_that_differs_from_the_from_address(): void
    {
        $this->campaignMail(['username' => 'sale@acme.test', 'from_address' => 'sales@acme.test']);
        $this->app->instance(\App\Services\CampaignMailer::class, new class(app(CampaignSettings::class)) extends \App\Services\CampaignMailer
        {
            public function send(string $to, CampaignMail $mail): void
            {
                throw new \RuntimeException('Expected response code "235" but got code "535", with message "535 Incorrect authentication data".');
            }
        });

        $this->actingAs($this->admin())->post(route('campaign-setup.test-email'), ['test_email' => 'me@example.com'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'correct CAMPAIGN_MAIL_USERNAME / CAMPAIGN_MAIL_PASSWORD in .env') && str_contains($m, 'the username (sale@acme.test) is different from the From address (sales@acme.test)'));
    }

    public function test_campaign_email_is_sent_through_the_campaign_mail_login(): void
    {
        $this->campaignMail(['username' => 'login@acme.test']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('campaign-setup.edit'))->assertOk()
            ->assertSee('Acme News &lt;news@acme.test&gt; via login@acme.test at 127.0.0.1', false)
            ->assertSee('127.0.0.1:1')
            ->assertSee('password set')
            ->assertSee('are different mailboxes')
            ->assertDontSee('env-secret');

        // It really connects with that login.
        $this->createCampaign($admin, 1);
        $this->assertStringContainsString('127.0.0.1', CampaignRecipient::firstOrFail()->error);
    }

    public function test_tests_never_see_the_real_campaign_mail_login(): void
    {
        $this->assertNull(app(CampaignSettings::class)->envMail(), 'phpunit.xml must blank CAMPAIGN_MAIL_HOST so tests cannot send real email');
    }
}
