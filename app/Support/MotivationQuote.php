<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MotivationQuote
{
    public static function current(): array
    {
        return Cache::remember('motivation_quote', now()->addHour(), function () {
            $apiUrl = Setting::get('motivation_quote_api_url') ?: 'https://api.quotable.io/random';

            try {
                $response = Http::timeout(4)->get($apiUrl);

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'text' => $data['content'] ?? $data['quote'] ?? $data[0]['q'] ?? 'Keep pushing forward.',
                        'author' => $data['author'] ?? $data[0]['a'] ?? 'Unknown',
                    ];
                }
            } catch (\Throwable) {
                // fall through to default quote
            }

            return [
                'text' => 'Success is the sum of small efforts, repeated day in and day out.',
                'author' => 'Robert Collier',
            ];
        });
    }
}
