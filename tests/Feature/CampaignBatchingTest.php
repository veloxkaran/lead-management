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

    private function contacts(int $count): string
    {
        return collect(range(1, $count))->map(fn ($i) => "person{$i}@example.com")->implode("\n");
    }

    private function createCampaign(User $user, int $contacts, array $overrides = []): Campaign
    {
        $this->actingAs($user)->post(route('campaigns.store'), [
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
        $settings->set('campaign_reply_to', 'sales@acme.test');
        $settings->set('campaign_from_name', 'Ram from Acme');

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
                && $mail->headers()->text['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click'
                // Set by CampaignMailer at send time, so on the properties rather than the envelope.
                && $mail->replyTo == [['address' => 'sales@acme.test', 'name' => null]]
                && $mail->from == [['address' => config('mail.from.address'), 'name' => 'Ram from Acme']];
        });
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

    public function test_email_setup_saves_sender_signature_and_speed(): void
    {
        $admin = $this->admin();
        $settings = app(CampaignSettings::class);

        $this->actingAs($admin)->put(route('campaign-setup.update-email'), [
            'campaign_email_mode' => 'smtp',
            'campaign_smtp_host' => 'mail.acme.test',
            'campaign_smtp_port' => 465,
            'campaign_smtp_encryption' => 'ssl',
            'campaign_smtp_username' => 'campaigns@acme.test',
            'campaign_smtp_password' => 'smtp-secret',
            'campaign_from_name' => 'Acme Sales',
            'campaign_reply_to' => 'sales@acme.test',
            'campaign_signature' => '<p>Ram<script>alert(1)</script></p>',
            'campaign_footer' => 'Acme, Kathmandu',
            'campaign_track_opens' => '0',
            'campaign_batch_size' => 50,
            'campaign_emails_per_minute' => 10,
            'campaign_batch_pause_minutes' => 30,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('smtp', $settings->emailMode());
        $this->assertSame('smtp-secret', $settings->smtpPassword());
        $this->assertDatabaseMissing('settings', ['value' => 'smtp-secret']);
        $this->assertStringNotContainsString('<script', $settings->signature());
        $this->assertStringContainsString('Ram', $settings->signature());
        $this->assertFalse($settings->tracksOpens());
        $this->assertSame(['batch_size' => 50, 'per_minute' => 10, 'pause_minutes' => 30], array_intersect_key($settings->sendOptions(\App\Enums\CampaignChannel::Email), array_flip(['batch_size', 'per_minute', 'pause_minutes'])));

        $page = $this->actingAs($admin)->get(route('campaign-setup.edit'))->assertOk();
        $page->assertDontSee('smtp-secret')->assertSee('saved — leave blank to keep')->assertSee('Acme Sales &lt;campaigns@acme.test&gt; via dedicated SMTP (mail.acme.test)', false);

        // SMTP mode needs its server details.
        $this->actingAs($admin)->put(route('campaign-setup.update-email'), [
            'campaign_email_mode' => 'smtp', 'campaign_batch_size' => 100, 'campaign_emails_per_minute' => 20, 'campaign_batch_pause_minutes' => 15,
        ])->assertSessionHasErrors(['campaign_smtp_host', 'campaign_smtp_port', 'campaign_smtp_encryption']);

        // Speed limits are enforced.
        $this->actingAs($admin)->put(route('campaign-setup.update-email'), [
            'campaign_email_mode' => 'system', 'campaign_batch_size' => 5000, 'campaign_emails_per_minute' => 500, 'campaign_batch_pause_minutes' => 15,
        ])->assertSessionHasErrors(['campaign_batch_size', 'campaign_emails_per_minute']);

        // Saving the SMS tab leaves the email setup alone.
        $this->actingAs($admin)->put(route('campaign-setup.update'), ['sms_method' => 'POST', 'sms_format' => 'form', 'sms_auth_mode' => 'param'])->assertSessionHasNoErrors();
        $this->assertSame('smtp', $settings->emailMode());
    }

    public function test_campaign_email_goes_through_the_dedicated_smtp_login(): void
    {
        $settings = app(CampaignSettings::class);
        $settings->set('campaign_email_mode', 'smtp');
        $settings->set('campaign_smtp_host', '127.0.0.1');
        $settings->set('campaign_smtp_port', '1');
        $settings->set('campaign_smtp_encryption', 'none');
        $settings->set('campaign_from_address', 'campaigns@example.com');

        $this->createCampaign($this->admin(), 1);

        $recipient = CampaignRecipient::firstOrFail();
        $this->assertSame(CampaignRecipientStatus::Failed, $recipient->status);
        $this->assertStringContainsString('127.0.0.1', $recipient->error);
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

        $other = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $this->actingAs($other)->get(route('campaigns.export', $campaign))->assertForbidden();
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
        app(CampaignSettings::class)->set('campaign_from_address', 'news@acme.test');
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
}
