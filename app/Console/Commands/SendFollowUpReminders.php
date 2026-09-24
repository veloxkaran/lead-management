<?php

namespace App\Console\Commands;

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Notifications\FollowUpReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SendFollowUpReminders extends Command
{
    protected $signature = 'follow-ups:send-reminders';

    protected $description = 'Send reminders for follow-ups whose reminder window has arrived';

    public function handle(): int
    {
        $sent = 0;
        $failed = 0;

        FollowUp::where('status', FollowUpStatus::Pending)
            ->with('lead.assignedUser', 'lead.creator', 'creator')
            ->chunkById(100, function ($followUps) use (&$sent, &$failed) {
                foreach ($followUps as $followUp) {
                    $dueAt = Carbon::parse($followUp->follow_up_date->toDateString().' '.$followUp->follow_up_time)
                        ->subMinutes($followUp->reminder_minutes_before);

                    if ($dueAt->isFuture()) {
                        continue;
                    }

                    $recipient = $followUp->lead?->assignedUser ?? $followUp->creator;

                    // One failed send (e.g. SMTP down) must not abort the
                    // whole run — otherwise the same follow-up throws every
                    // five minutes and every one after it is never reached.
                    // Left pending so the next run retries it.
                    if ($recipient) {
                        try {
                            $recipient->notify(new FollowUpReminderNotification($followUp));
                        } catch (Throwable $e) {
                            report($e);
                            $failed++;

                            continue;
                        }
                    }

                    $followUp->update(['status' => FollowUpStatus::Sent, 'notified_at' => now()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} follow-up reminder(s).");

        if ($failed) {
            $this->error("Failed to send {$failed} follow-up reminder(s) — see the log.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
