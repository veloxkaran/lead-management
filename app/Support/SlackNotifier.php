<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNotifier
{
    public function send(string $message): bool
    {
        $webhookUrl = Setting::get('slack_webhook_url');

        if (blank($webhookUrl)) {
            return false;
        }

        try {
            $response = Http::timeout(5)->post($webhookUrl, ['text' => $message]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Slack webhook notification failed: '.$e->getMessage());

            return false;
        }
    }
}
