<?php

namespace App\Console\Commands;

use App\Jobs\PostSlackWriteActivity;
use App\Services\DailyActivitySummary;
use App\Support\SlackNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Posts "what happened in the CRM today" to Slack — scheduled daily at
 * 4:20 PM (routes/console.php). Goes to the write-activity webhook
 * (SLACK_ACTIVITY_WEBHOOK_URL), or the Settings page's Slack webhook when
 * that isn't set.
 */
class SendDailyActivitySummary extends Command
{
    protected $signature = 'crm:daily-activity-summary
        {--date= : The day to summarize (YYYY-MM-DD); defaults to today}
        {--dry-run : Print the message instead of posting it}';

    protected $description = 'Post a summary of what happened in the CRM today to Slack';

    public function handle(DailyActivitySummary $summary, SlackNotifier $slack): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $text = $summary->slackText($day);

        if ($this->option('dry-run')) {
            $this->line($text);

            return self::SUCCESS;
        }

        if (filled(config('services.slack.activity_webhook_url'))) {
            // A failed post (Slack down/rate limited) is retried from the queue.
            if (! PostSlackWriteActivity::deliver($text)) {
                PostSlackWriteActivity::dispatch($text);
                $this->warn('Slack post failed — queued for retry.');

                return self::SUCCESS;
            }
        } elseif (! $slack->send($text)) {
            $this->warn('No Slack webhook is configured — summary not sent.');

            return self::SUCCESS;
        }

        $this->info('CRM daily summary posted to Slack.');

        return self::SUCCESS;
    }
}
