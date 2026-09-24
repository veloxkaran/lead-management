<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Jobs\SendClientNotificationEmail;
use App\Mail\ClientNotificationMail;
use App\Models\EmailLog;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RequirementClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_requirement_queues_a_notification_job_rather_than_sending_inline(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['email' => 'jordan@acme.test']);

        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => 'Needs a custom integration',
            'priority' => 'high',
        ])->assertRedirect();

        Queue::assertPushedOn('emails', SendClientNotificationEmail::class, function (SendClientNotificationEmail $job) {
            return $job->log->template_key === 'requirement_created' && $job->log->to_email === 'jordan@acme.test';
        });

        // Logged as pending straight away; nothing is sent until a worker
        // processes the queue.
        $this->assertDatabaseCount('email_logs', 1);
        $this->assertSame(\App\Enums\EmailLogStatus::Pending, EmailLog::first()->status);
        $this->assertNull(EmailLog::first()->sent_at);
    }

    public function test_rich_text_requirement_is_emailed_as_clean_plain_text(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => '<p><span style="color:rgb(0,0,0);">Leave &amp; encashment gaps:</span></p>'
                .'<ol><li><strong>Carry forward</strong> not supported</li><li>90-day limit lapses</li></ol>',
            'priority' => 'high',
        ])->assertRedirect();

        Mail::assertSent(ClientNotificationMail::class, function (ClientNotificationMail $mail) {
            return str_contains($mail->renderedBody, "Leave & encashment gaps:\n\n1. Carry forward not supported\n2. 90-day limit lapses")
                && ! str_contains($mail->renderedBody, '<')
                && ! str_contains($mail->renderedBody, '&amp;');
        });

        $superAdmin = User::factory()->superAdmin()->create();
        $log = EmailLog::sole();

        $this->actingAs($superAdmin)->get(route('email-logs.show', $log))
            ->assertOk()
            ->assertDontSee('&lt;span', false)
            ->assertDontSee('rgb(0,0,0)', false)
            ->assertSee('Requirement — Leave &amp; encashment gaps: 1. Carry forward', false);

        $this->actingAs($superAdmin)->get(route('email-logs.index'))
            ->assertOk()
            ->assertDontSee('&lt;p&gt;', false);
    }

    public function test_pasted_table_survives_saving_and_is_emailed_row_by_row(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['email' => 'client@example.test']);

        // Shape of what Quill's table module produces for a pasted table.
        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => '<table><tbody>'
                .'<tr><td data-row="r1">Requirement (Client)</td><td data-row="r1">Gap</td></tr>'
                .'<tr><td data-row="r2"><strong>Substitute Leave</strong> encashed</td><td data-row="r2">❌ Not supported</td></tr>'
                .'</tbody></table>',
            'priority' => 'medium',
        ])->assertRedirect();

        $requirement = \App\Models\Requirement::sole();
        $this->assertStringContainsString('<table>', $requirement->requirement);
        $this->assertStringContainsString('<td><strong>Substitute Leave</strong> encashed</td>', $requirement->requirement);

        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertOk()
            ->assertSee('<td>Requirement (Client)</td>', false);

        Mail::assertSent(ClientNotificationMail::class, fn (ClientNotificationMail $mail) => str_contains(
            $mail->renderedBody,
            "Requirement (Client) | Gap\nSubstitute Leave encashed | ❌ Not supported"
        ));
    }

    public function test_creating_a_requirement_emails_the_leads_contact(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp', 'contact_person' => 'Jordan Smith', 'email' => 'jordan@acme.test']);

        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => 'Needs a custom integration',
            'priority' => 'high',
        ])->assertRedirect();

        Mail::assertSent(ClientNotificationMail::class, function (ClientNotificationMail $mail) {
            return $mail->hasTo('jordan@acme.test')
                && str_contains($mail->renderedSubject, 'Acme Corp')
                && str_contains($mail->renderedBody, 'Jordan Smith')
                && str_contains($mail->renderedBody, 'Needs a custom integration');
        });

        $log = EmailLog::first();
        $this->assertNotNull($log);
        $this->assertSame('jordan@acme.test', $log->to_email);
        $this->assertSame('requirement_created', $log->template_key);
        $this->assertTrue($log->status === \App\Enums\EmailLogStatus::Sent);

        // The rich-text sanitizer wraps plain text in a <p> on save, so
        // match on content rather than exact equality.
        $requirement = Requirement::where('requirement', 'like', '%Needs a custom integration%')->first();
        $this->assertTrue($log->related->is($requirement));
    }

    public function test_changing_status_emails_the_leads_contact_with_old_and_new_status(): void
    {
        Mail::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create(['email' => 'client@example.test']);
        $requirement = Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending]);

        $this->actingAs($superAdmin)->patch(route('requirements.status.update', $requirement), [
            'status' => RequirementStatus::InProgress->value,
            'note' => 'Started work.',
        ])->assertRedirect();

        Mail::assertSent(ClientNotificationMail::class, function (ClientNotificationMail $mail) {
            return $mail->hasTo('client@example.test')
                && str_contains($mail->renderedBody, 'Pending')
                && str_contains($mail->renderedBody, 'In Progress');
        });

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'client@example.test',
            'template_key' => 'requirement_status_changed',
        ]);
    }

    public function test_resubmitting_the_same_status_sends_no_email(): void
    {
        Mail::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create(['email' => 'client@example.test']);
        $requirement = Requirement::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending]);

        $this->actingAs($superAdmin)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => RequirementStatus::Pending->value,
        ])->assertRedirect();

        Mail::assertNotSent(ClientNotificationMail::class);
        $this->assertDatabaseCount('email_logs', 0);
    }

    public function test_no_email_is_sent_when_the_lead_has_no_email_on_file(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['email' => null]);

        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => 'Some requirement',
            'priority' => 'medium',
        ])->assertRedirect();

        Mail::assertNotSent(ClientNotificationMail::class);
        $this->assertDatabaseCount('email_logs', 0);
    }

    public function test_email_notifications_can_be_globally_disabled(): void
    {
        Mail::fake();
        Setting::set('notifications_email_enabled', '0');

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => 'Some requirement',
            'priority' => 'medium',
        ])->assertRedirect();

        Mail::assertNotSent(ClientNotificationMail::class);
    }
}
