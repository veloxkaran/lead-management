<?php

namespace Tests\Feature;

use App\Enums\CampaignRecipientStatus;
use App\Enums\EmailLogStatus;
use App\Jobs\SendClientNotificationEmail;
use App\Mail\ClientNotificationMail;
use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\User;
use App\Support\EmailFailureReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    private function log(array $attributes = []): EmailLog
    {
        return EmailLog::create([
            'to_email' => 'client@example.com',
            'subject' => 'Your ticket was updated',
            'body' => 'Hello',
            'status' => EmailLogStatus::Pending,
            ...$attributes,
        ]);
    }

    public function test_notification_emails_are_marked_delivered_when_opened(): void
    {
        Mail::fake();
        $log = $this->log();
        $this->assertNotNull($log->tracking_token, 'every log gets a tracking token');

        app()->call([new SendClientNotificationEmail($log), 'handle']);

        $this->assertSame(EmailLogStatus::Sent, $log->fresh()->status);
        Mail::assertSent(ClientNotificationMail::class, fn (ClientNotificationMail $mail) => $mail->trackingUrl === route('email-logs.track-open', $log->tracking_token)
            && str_contains($mail->render(), 'src="'.route('email-logs.track-open', $log->tracking_token).'"'));

        $response = $this->get(route('email-logs.track-open', $log->tracking_token));
        $response->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertEmpty($response->headers->getCookies(), 'no session for a tracking image');

        $log->refresh();
        $this->assertSame(EmailLogStatus::Delivered, $log->status);
        $this->assertNotNull($log->delivered_at);
        $this->assertStringStartsWith('Opened by the recipient on', $log->remarks());

        // A failed email doesn't become "delivered", and unknown tokens just get the image.
        $failed = $this->log(['status' => EmailLogStatus::Failed, 'error' => 'boom']);
        $this->get(route('email-logs.track-open', $failed->tracking_token))->assertOk();
        $this->assertSame(EmailLogStatus::Failed, $failed->fresh()->status);
        $this->get(route('email-logs.track-open', 'nope'))->assertOk();
    }

    public function test_older_logs_without_a_token_get_one_when_sent(): void
    {
        Mail::fake();
        $log = $this->log();
        $log->forceFill(['tracking_token' => null])->saveQuietly();

        app()->call([new SendClientNotificationEmail($log->fresh()), 'handle']);

        $this->assertNotNull($log->fresh()->tracking_token);
    }

    public function test_errors_are_explained_in_plain_words(): void
    {
        $this->assertStringContainsString('rejected the login', EmailFailureReason::explain('Failed to authenticate on SMTP server with username "x" … got code "535", with message "535 Incorrect authentication data".'));
        $this->assertStringContainsString("doesn't exist", EmailFailureReason::explain('Expected response code "250" but got code "550", with message "550 5.1.1 <nobody@acme.test>: Recipient address rejected: User unknown"'));
        $this->assertStringContainsString('mailbox is full', EmailFailureReason::explain('552 5.2.2 Mailbox full'));
        $this->assertStringContainsString('too large', EmailFailureReason::explain('552 5.3.4 Message size exceeds fixed limit'));
        $this->assertStringContainsString('spam', EmailFailureReason::explain('554 5.7.1 Message rejected as spam'));
        $this->assertStringContainsString("Couldn't reach", EmailFailureReason::explain('Connection could not be established with host "mail.acme.test:465": Connection refused'));
        $this->assertNull(EmailFailureReason::explain('Something odd happened'));
        $this->assertNull(EmailFailureReason::explain(null));
    }

    public function test_the_email_log_shows_status_counts_and_remarks(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->log(['to_email' => 'opened@example.com', 'status' => EmailLogStatus::Delivered, 'sent_at' => now(), 'delivered_at' => now()]);
        $this->log(['to_email' => 'sent@example.com', 'status' => EmailLogStatus::Sent, 'sent_at' => now()]);
        $this->log(['to_email' => 'failed@example.com', 'status' => EmailLogStatus::Failed, 'error' => 'Expected response code "235" but got code "535", with message "535 Incorrect authentication data".']);
        $stuck = $this->log(['to_email' => 'stuck@example.com']);
        $stuck->forceFill(['created_at' => now()->subHour()])->save();

        $page = $this->actingAs($admin)->get(route('email-logs.index'))->assertOk();
        $page->assertSee('Remarks')
            ->assertSeeInOrder(['All', '4', 'Pending', '1', 'Sent', '1', 'Delivered', '1', 'Failed', '1'])
            ->assertSee('Opened by the recipient on')
            ->assertSee('Accepted by the mail server')
            ->assertSee('The mail server rejected the login')
            ->assertSee('535 Incorrect authentication data')
            ->assertSee('the queue worker (cron) may not be running');

        $this->actingAs($admin)->get(route('email-logs.index', ['status' => 'delivered']))->assertOk()
            ->assertSee('opened@example.com')->assertDontSee('sent@example.com');

        $failed = EmailLog::where('to_email', 'failed@example.com')->first();
        $this->actingAs($admin)->get(route('email-logs.show', $failed))->assertOk()
            ->assertSee('Remarks')->assertSee('The mail server rejected the login')->assertSee('535 Incorrect authentication data');
    }

    public function test_the_campaign_send_log_explains_each_recipient(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $campaign = Campaign::factory()->create(['created_by' => $admin->id, 'recipient_count' => 4, 'batch_count' => 1]);
        $campaign->recipients()->create(['batch' => 1, 'address' => 'opened@example.com', 'status' => CampaignRecipientStatus::Delivered, 'sent_at' => now(), 'delivered_at' => now()]);
        $campaign->recipients()->create(['batch' => 1, 'address' => 'sent@example.com', 'status' => CampaignRecipientStatus::Sent, 'sent_at' => now()]);
        $campaign->recipients()->create(['batch' => 1, 'address' => 'nobody@example.com', 'status' => CampaignRecipientStatus::Failed, 'error' => '550 5.1.1 User unknown']);
        $campaign->recipients()->create(['batch' => 1, 'address' => 'gone@example.com', 'status' => CampaignRecipientStatus::Cancelled, 'error' => 'Unsubscribed before this was sent.']);

        $this->actingAs($admin)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Remarks')
            ->assertSee('Opened by the recipient on')
            ->assertSee('Accepted by the mail server')
            ->assertSee("The recipient address doesn't exist")
            ->assertSee('550 5.1.1 User unknown')
            ->assertSee('Unsubscribed before this was sent.');

        $csv = $this->actingAs($admin)->get(route('campaigns.export', $campaign))->assertOk()->streamedContent();
        $this->assertStringContainsString('Remarks', strtok($csv, "\n"));
        $this->assertStringContainsString("The recipient address doesn't exist", $csv);
    }
}
