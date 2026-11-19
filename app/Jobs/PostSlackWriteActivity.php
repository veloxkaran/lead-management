<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts one batched write-activity message (see SlackWriteActivity) to the
 * Slack webhook. SlackWriteActivity::flush() first tries deliver() directly
 * (it runs after the response is sent, so no user waits on it, and no queue
 * worker is needed); only a failed post is queued as this job, for retries.
 */
class PostSlackWriteActivity implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $text)
    {
    }

    /** One immediate attempt; false means it should be retried via the queue. */
    public static function deliver(string $text): bool
    {
        $url = config('services.slack.activity_webhook_url');

        if (blank($url)) {
            return true;
        }

        try {
            return Http::timeout(5)->post($url, ['text' => $text])->successful();
        } catch (\Throwable $e) {
            Log::warning('Slack write-activity post failed: '.$e->getMessage());

            return false;
        }
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(): void
    {
        $url = config('services.slack.activity_webhook_url');

        if (blank($url)) {
            return;
        }

        $response = Http::timeout(10)->post($url, ['text' => $this->text]);

        // Slack rate limit: wait as long as it asks, then try again.
        if ($response->status() === 429) {
            $this->release((int) $response->header('Retry-After') ?: 30);

            return;
        }

        $response->throw();
    }
}
