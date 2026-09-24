<?php

namespace Tests\Feature;

use App\Enums\FollowUpStatus;
use App\Enums\ReminderType;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendFollowUpRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function dueFollowUp(User $user): FollowUp
    {
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id]);

        return FollowUp::factory()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'status' => FollowUpStatus::Pending,
            'reminder_type' => ReminderType::Email,
            'follow_up_date' => now()->subDay()->toDateString(),
            'follow_up_time' => '09:00:00',
            'reminder_minutes_before' => 15,
        ]);
    }

    public function test_due_reminders_are_sent_and_marked_sent(): void
    {
        config(['mail.default' => 'array']);

        $followUp = $this->dueFollowUp(User::factory()->create());

        $this->artisan('follow-ups:send-reminders')->assertSuccessful();

        $this->assertSame(FollowUpStatus::Sent, $followUp->fresh()->status);
    }

    public function test_a_mail_failure_does_not_abort_the_run_and_leaves_the_reminder_pending(): void
    {
        // Unreachable SMTP server — every send throws.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.scheme' => 'smtp',
        ]);

        $user = User::factory()->create();
        $first = $this->dueFollowUp($user);
        $second = $this->dueFollowUp($user);

        $this->artisan('follow-ups:send-reminders')->assertFailed();

        // Both were attempted (not just the first) and stay pending for retry.
        $this->assertSame(FollowUpStatus::Pending, $first->fresh()->status);
        $this->assertSame(FollowUpStatus::Pending, $second->fresh()->status);
    }
}
