<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Mail\ClientNotificationMail;
use App\Models\EmailLog;
use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportTicketClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_ticket_for_a_lead_emails_the_leads_contact(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp', 'contact_person' => 'Jordan Smith', 'email' => 'jordan@acme.test']);

        $this->actingAs($user)->post(route('leads.support-tickets.store', $lead), [
            'subject' => 'Cannot log in',
            'priority' => 'high',
        ])->assertRedirect();

        Mail::assertSent(ClientNotificationMail::class, function (ClientNotificationMail $mail) {
            return $mail->hasTo('jordan@acme.test')
                && str_contains($mail->renderedBody, 'Cannot log in');
        });

        $log = EmailLog::first();
        $this->assertNotNull($log);
        $this->assertSame('support_ticket_created', $log->template_key);

        $ticket = SupportTicket::firstWhere('subject', 'Cannot log in');
        $this->assertTrue($log->related->is($ticket));
    }

    public function test_ticket_with_no_lead_sends_no_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('support-tickets.store'), [
            'subject' => 'Unlinked ticket',
            'priority' => 'medium',
        ])->assertRedirect();

        Mail::assertNotSent(ClientNotificationMail::class);
        $this->assertDatabaseCount('email_logs', 0);
    }

    public function test_status_change_via_edit_emails_the_leads_contact(): void
    {
        Mail::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create(['email' => 'client@example.test']);
        $ticket = SupportTicket::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => RequirementStatus::InProgress->value,
        ])->assertRedirect();

        Mail::assertSent(ClientNotificationMail::class, function (ClientNotificationMail $mail) {
            return $mail->hasTo('client@example.test')
                && str_contains($mail->renderedBody, 'Pending')
                && str_contains($mail->renderedBody, 'In Progress');
        });

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'client@example.test',
            'template_key' => 'support_ticket_status_changed',
        ]);
    }

    public function test_resubmitting_the_same_status_sends_no_email(): void
    {
        Mail::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create(['email' => 'client@example.test']);
        $ticket = SupportTicket::factory()->create(['lead_id' => $lead->id, 'status' => RequirementStatus::Pending]);

        $this->actingAs($superAdmin)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'details' => $ticket->details,
            'priority' => $ticket->priority->value,
            'status' => RequirementStatus::Pending->value,
        ])->assertRedirect();

        Mail::assertNotSent(ClientNotificationMail::class);
        $this->assertDatabaseCount('email_logs', 0);
    }
}
