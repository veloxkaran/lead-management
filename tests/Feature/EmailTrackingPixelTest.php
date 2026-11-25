<?php

namespace Tests\Feature;

use App\Enums\EmailLogStatus;
use App\Jobs\SendClientNotificationEmail;
use App\Mail\ClientNotificationMail;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\CampaignMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tracking images hurt inbox delivery (Gmail refused notification emails
 * whose pixel pointed at another domain over plain http), so they are off
 * for notifications by default and only ever served over HTTPS.
 */
class EmailTrackingPixelTest extends TestCase
{
    use RefreshDatabase;

    private function sendNotification(): ClientNotificationMail
    {
        Mail::fake();
        $log = EmailLog::create(['to_email' => 'client@example.com', 'subject' => 'Update', 'body' => 'Hello', 'status' => EmailLogStatus::Pending]);

        app()->call([new SendClientNotificationEmail($log), 'handle']);

        $sent = null;
        Mail::assertSent(ClientNotificationMail::class, function (ClientNotificationMail $mail) use (&$sent) {
            $sent = $mail;

            return true;
        });

        return $sent;
    }

    public function test_notification_emails_have_no_tracking_image_by_default(): void
    {
        config(['app.url' => 'https://crm.hajirhr.com']);

        $mail = $this->sendNotification();

        $this->assertNull($mail->trackingUrl);
        $this->assertStringNotContainsString('/track/', $mail->render());
    }

    public function test_no_tracking_image_over_plain_http_even_when_switched_on(): void
    {
        config(['app.url' => 'http://corporate.veloxlabs.net', 'mail.track_notification_opens' => true]);

        $this->assertNull($this->sendNotification()->trackingUrl);

        $campaignMail = app(CampaignMailer::class)->compose('Hi', 'Body', 'token123', trackOpens: true);
        $this->assertNull($campaignMail->trackingUrl);
    }

    public function test_mail_links_url_puts_links_on_the_sender_domain(): void
    {
        config([
            'app.url' => 'http://corporate.veloxlabs.net',
            'mail.links_url' => 'https://track.hajirhr.com/',
            'mail.track_notification_opens' => true,
        ]);

        $this->assertStringStartsWith('https://track.hajirhr.com/track/notification/', $this->sendNotification()->trackingUrl);

        $campaignMail = app(CampaignMailer::class)->compose('Hi', 'Body', 'token123', trackOpens: true);
        $this->assertSame('https://track.hajirhr.com/track/email/token123', $campaignMail->trackingUrl);
        $this->assertSame('https://track.hajirhr.com/unsubscribe/token123', $campaignMail->unsubscribeUrl);
    }

    public function test_domain_check_flags_http_and_foreign_link_domains(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $linksCheck = fn () => collect($this->actingAs($admin)->getJson(route('campaign-setup.domain-check'))->json('checks'))->firstWhere('key', 'links');

        config(['app.url' => 'http://corporate.veloxlabs.net', 'mail.links_url' => null]);
        $this->assertSame('warn', $linksCheck()['status']);
        $this->assertStringContainsString('plain http', $linksCheck()['detail']);

        config(['app.url' => 'https://corporate.veloxlabs.net']);
        $this->assertSame('warn', $linksCheck()['status']);
        $this->assertStringContainsString('MAIL_LINKS_URL', $linksCheck()['detail']);

        config(['mail.links_url' => 'https://track.hajirhr.com']);
        $this->assertSame('ok', $linksCheck()['status']);
    }
}
