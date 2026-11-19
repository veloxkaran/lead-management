<?php

namespace Tests\Feature;

use App\Enums\AnnouncementAudience;
use App\Enums\CampaignChannel;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\EmailLogStatus;
use App\Jobs\SendCampaignMessage;
use App\Jobs\SendClientNotificationEmail;
use App\Mail\ClientNotificationMail;
use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\CampaignUnsubscribe;
use App\Models\EmailLog;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Support\MailRetry;
use App\Support\PublicUrl;
use App\Support\RecipientEmailCheck;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailDeliverabilityTest extends TestCase
{
    use RefreshDatabase;

    /** A checker whose DNS answers are scripted: host => [type => records]. */
    private function checkerWithDns(array $dns): RecipientEmailCheck
    {
        config(['mail.verify_recipient_domains' => true]);

        return new class($dns) extends RecipientEmailCheck
        {
            public function __construct(private array $dns)
            {
            }

            protected function records(string $host, int $type): array
            {
                return $this->dns[$host][$type] ?? [];
            }
        };
    }

    private function announcementLog(string $to = 'owner@acme-client.com'): EmailLog
    {
        $announcement = Announcement::create([
            'title' => 'Dashain offer',
            'content' => 'Body',
            'audience' => 'all_leads',
            'created_by' => User::factory()->superAdmin()->create()->id,
        ]);

        $log = new EmailLog(['to_email' => $to, 'subject' => 'Dashain offer', 'body' => 'Hi there', 'status' => EmailLogStatus::Pending]);
        $log->related()->associate($announcement);
        $log->save();

        return $log;
    }

    // ---- Recipient address check -------------------------------------------

    public function test_badly_formed_typo_and_throwaway_addresses_are_rejected_with_a_reason(): void
    {
        $check = new RecipientEmailCheck;

        $this->assertSame('invalid', $check->check('not-an-email')['status']);
        $this->assertSame('invalid', $check->check('ram@@acme.com')['status']);
        $this->assertSame('invalid', $check->check('')['status']);

        $typo = $check->check('Ram@Gmial.com');
        $this->assertSame('invalid', $typo['status']);
        $this->assertStringContainsString('did you mean ram@gmail.com', $typo['reason']);

        $this->assertStringContainsString('throwaway', $check->check('x@mailinator.com')['reason']);
        $this->assertSame('valid', $check->check('ram@gmail.com')['status']);
    }

    public function test_domain_must_be_able_to_receive_mail(): void
    {
        Cache::flush();
        $check = $this->checkerWithDns([
            'gmail.com' => [DNS_MX => [['target' => 'gmail-smtp-in.l.google.com']]],
            'acme.com' => [DNS_MX => [['target' => 'mail.acme.com']]],
            'nomx-but-a.com' => [DNS_A => [['ip' => '203.0.113.5']]],
            'nullmx.com' => [DNS_MX => [['target' => '']]],
        ]);

        $this->assertSame('valid', $check->check('a@acme.com')['status']);
        $this->assertSame('valid', $check->check('a@nomx-but-a.com')['status'], 'an A record is an implicit mail server (RFC 5321)');
        $this->assertStringContainsString("doesn't accept email", $check->check('a@nullmx.com')['reason']);

        $dead = $check->check('a@no-such-domain-xyz.com');
        $this->assertSame('invalid', $dead['status']);
        $this->assertStringContainsString('no mail server', $dead['reason']);
    }

    public function test_a_dns_outage_on_our_side_never_blocks_a_real_address(): void
    {
        Cache::flush();
        $check = $this->checkerWithDns([]); // nothing resolves — not even gmail.com

        $result = $check->check('a@acme.com');

        $this->assertSame('unknown', $result['status']);
        $this->assertTrue($check->isDeliverable('a@acme.com'));
        $this->assertNull(Cache::get('recipient-domain:acme.com'), 'an outage result is not cached');
    }

    // ---- Retrying ------------------------------------------------------------

    public function test_only_failures_where_nothing_was_sent_are_retryable(): void
    {
        $this->assertTrue(MailRetry::isRetryable(new RuntimeException('Connection could not be established with host "ssl://mail.hajirhr.com:465": stream_socket_client(): php_network_getaddresses: getaddrinfo failed')));
        $this->assertTrue(MailRetry::isRetryable(new RuntimeException('Expected response code "250" but got code "451", with message "451 4.7.1 Try again later".')));

        $this->assertFalse(MailRetry::isRetryable(new RuntimeException('Expected response code "250" but got code "550", with message "550 5.1.1 User unknown".')));
        $this->assertFalse(MailRetry::isRetryable(new RuntimeException('Connection to "mail.hajirhr.com:465" timed out.')), 'mid-message: it may already have been accepted');

        $this->assertSame([60, 300, 900, null], [MailRetry::delayFor(1), MailRetry::delayFor(2), MailRetry::delayFor(3), MailRetry::delayFor(4)]);
    }

    public function test_an_unreachable_mail_server_is_retried_then_marked_failed(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.scheme' => 'smtp']);
        $log = EmailLog::create(['to_email' => 'client@acme.com', 'subject' => 'Ticket updated', 'body' => 'Hello', 'status' => EmailLogStatus::Pending]);

        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(60);

        $job = new SendClientNotificationEmail($log);
        $job->setJob($queueJob);
        app()->call([$job, 'handle']);

        $log->refresh();
        $this->assertSame(EmailLogStatus::Pending, $log->status, 'still waiting for its retry');
        $this->assertStringContainsString('retrying automatically', $log->remarks());

        // Last attempt: no more retries — recorded as failed.
        $lastTry = Mockery::mock(Job::class);
        $lastTry->shouldReceive('attempts')->andReturn(4);
        $lastTry->shouldNotReceive('release');

        $job = new SendClientNotificationEmail($log);
        $job->setJob($lastTry);
        app()->call([$job, 'handle']);

        $this->assertSame(EmailLogStatus::Failed, $log->fresh()->status);
    }

    // ---- Skipping undeliverable / unsubscribed recipients --------------------

    public function test_campaign_email_to_an_invalid_address_is_not_sent(): void
    {
        Mail::fake();
        $campaign = Campaign::factory()->create(['channel' => CampaignChannel::Email, 'status' => CampaignStatus::Sending, 'subject' => 'Hi', 'message' => 'Hello']);
        $recipient = $campaign->recipients()->create(['address' => 'ram@gmial.com', 'status' => CampaignRecipientStatus::Pending, 'tracking_token' => 'tok-typo']);

        app()->call([new SendCampaignMessage($recipient->id), 'handle']);

        $recipient->refresh();
        $this->assertSame(CampaignRecipientStatus::Failed, $recipient->status);
        $this->assertStringStartsWith('Not sent — ', $recipient->error);
        $this->assertStringContainsString('did you mean ram@gmail.com', $recipient->remarks());
        Mail::assertNothingSent();
    }

    public function test_announcement_to_an_invalid_address_is_not_sent(): void
    {
        Mail::fake();
        $log = $this->announcementLog('someone@mailinator.com');

        app()->call([new SendClientNotificationEmail($log), 'handle']);

        $this->assertSame(EmailLogStatus::Failed, $log->fresh()->status);
        $this->assertStringContainsString('throwaway', $log->fresh()->remarks());
        Mail::assertNothingSent();
    }

    // ---- Announcement email: anti-spam essentials + unsubscribe --------------

    public function test_announcement_email_has_text_part_footer_and_one_click_unsubscribe(): void
    {
        Mail::fake();
        Setting::set('campaign_footer', "Hajir HR Pvt. Ltd.\nKathmandu, Nepal");
        $log = $this->announcementLog();

        app()->call([new SendClientNotificationEmail($log), 'handle']);

        $unsubscribeUrl = route('campaigns.unsubscribe', $log->fresh()->tracking_token);

        Mail::assertSent(ClientNotificationMail::class, function (ClientNotificationMail $mail) use ($unsubscribeUrl) {
            $headers = $mail->headers()->text;
            $html = $mail->render();
            $text = view('emails.client-notification-text', ['body' => $mail->renderedBody, 'footer' => $mail->footer, 'unsubscribeUrl' => $mail->unsubscribeUrl, 'documentNames' => []])->render();

            return $headers['List-Unsubscribe'] === "<{$unsubscribeUrl}>"
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click'
                && str_contains($html, 'Kathmandu, Nepal')
                && str_contains($html, 'href="'.$unsubscribeUrl.'"')
                && str_contains($text, 'Unsubscribe: '.$unsubscribeUrl)
                && $mail->trackingUrl === null; // APP_URL is http://localhost in tests
        });
    }

    public function test_transactional_notifications_carry_no_unsubscribe(): void
    {
        Mail::fake();
        $log = EmailLog::create(['to_email' => 'client@acme.com', 'subject' => 'Ticket updated', 'body' => 'Hello', 'status' => EmailLogStatus::Pending]);

        app()->call([new SendClientNotificationEmail($log), 'handle']);

        Mail::assertSent(ClientNotificationMail::class, fn (ClientNotificationMail $mail) => $mail->unsubscribeUrl === null && $mail->headers()->text === []);
    }

    public function test_unsubscribing_from_an_announcement_stops_future_announcements(): void
    {
        Mail::fake();
        $log = $this->announcementLog('Owner@Acme-Client.com');
        Lead::factory()->create(['email' => 'owner@acme-client.com']);
        Lead::factory()->create(['email' => 'other@acme-client.com']);

        $this->get(route('campaigns.unsubscribe', $log->tracking_token))->assertOk()->assertSee('Unsubscribe?');
        $this->post(route('campaigns.unsubscribe.confirm', $log->tracking_token), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk()->assertSee("You're unsubscribed", false);

        $this->assertTrue(CampaignUnsubscribe::isUnsubscribed(CampaignChannel::Email, 'owner@acme-client.com'));

        // Left out of the next announcement's audience…
        $audience = app(AnnouncementService::class)->recipients(AnnouncementAudience::AllLeads)->pluck('email')->all();
        $this->assertSame(['other@acme-client.com'], $audience);

        // …and an email already queued for them is skipped.
        $queued = $this->announcementLog('owner@acme-client.com');
        app()->call([new SendClientNotificationEmail($queued), 'handle']);
        $this->assertSame(EmailLogStatus::Failed, $queued->fresh()->status);
        $this->assertStringContainsString('unsubscribed', $queued->fresh()->remarks());
        Mail::assertNothingSent();
    }

    public function test_links_in_emails_require_a_public_app_url(): void
    {
        $this->assertFalse(PublicUrl::isPublic('http://localhost'));
        $this->assertFalse(PublicUrl::isPublic('http://127.0.0.1:8000'));
        $this->assertFalse(PublicUrl::isPublic('http://192.168.1.20'));
        $this->assertFalse(PublicUrl::isPublic('http://crm.test'));
        $this->assertTrue(PublicUrl::isPublic('https://crm.hajirhr.com'));
        $this->assertTrue(PublicUrl::isPublic('http://190.92.174.131'));
    }
}
