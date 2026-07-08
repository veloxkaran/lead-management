<?php

namespace App\Console\Commands;

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Notifications\FollowUpReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendFollowUpReminders extends Command
{
    protected $signature = 'follow-ups:send-reminders';

    protected $description = 'Send reminders for follow-ups whose reminder window has arrived';

    public function handle(): int
    {
        $sent = 0;

        FollowUp::where('status', FollowUpStatus::Pending)
            ->with('lead.assignedUser', 'lead.creator', 'creator')
            ->chunkById(100, function ($followUps) use (&$sent) {
                foreach ($followUps as $followUp) {
                    $dueAt = Carbon::parse($followUp->follow_up_date->toDateString().' '.$followUp->follow_up_time)
                        ->subMinutes($followUp->reminder_minutes_before);

                    if ($dueAt->isFuture()) {
                        continue;
                    }

                    $recipient = $followUp->lead?->assignedUser ?? $followUp->creator;

                    if ($recipient) {
                        $recipient->notify(new FollowUpReminderNotification($followUp));
                    }

                    $followUp->update(['status' => FollowUpStatus::Sent, 'notified_at' => now()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
