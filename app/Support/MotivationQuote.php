<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MotivationQuote
{
    /**
     * Local fallback pool — used whenever no external API URL is
     * configured (Settings > Motivation Quote API URL) or that call fails.
     * Keeps the "changes every hour" guarantee independent of any network
     * dependency: rotating through this list by the current hour never
     * silently gets stuck on one quote the way a failed external fetch
     * would.
     */
    private const QUOTES = [
        ['text' => 'Success is the sum of small efforts, repeated day in and day out.', 'author' => 'Robert Collier'],
        ['text' => 'The way to get started is to quit talking and begin doing.', 'author' => 'Walt Disney'],
        ['text' => "Don't watch the clock; do what it does. Keep going.", 'author' => 'Sam Levenson'],
        ['text' => 'The future depends on what you do today.', 'author' => 'Mahatma Gandhi'],
        ['text' => "It always seems impossible until it's done.", 'author' => 'Nelson Mandela'],
        ['text' => 'Quality is not an act, it is a habit.', 'author' => 'Aristotle'],
        ['text' => 'Opportunities don\'t happen. You create them.', 'author' => 'Chris Grosser'],
        ['text' => 'The only way to do great work is to love what you do.', 'author' => 'Steve Jobs'],
        ['text' => "Whether you think you can or you think you can't, you're right.", 'author' => 'Henry Ford'],
        ['text' => 'Small daily improvements are the key to staggering long-term results.', 'author' => 'James Clear'],
        ['text' => "Don't count the days, make the days count.", 'author' => 'Muhammad Ali'],
        ['text' => 'Discipline is choosing between what you want now and what you want most.', 'author' => 'Abraham Lincoln'],
        ['text' => 'Action is the foundational key to all success.', 'author' => 'Pablo Picasso'],
        ['text' => 'You miss 100% of the shots you don\'t take.', 'author' => 'Wayne Gretzky'],
        ['text' => 'Great things are done by a series of small things brought together.', 'author' => 'Vincent van Gogh'],
        ['text' => 'Progress is impossible without change.', 'author' => 'George Bernard Shaw'],
        ['text' => 'What we do today, right now, will have an accumulated effect on all our tomorrows.', 'author' => 'Alexandra Stoddard'],
        ['text' => 'Focus on being productive instead of busy.', 'author' => 'Tim Ferriss'],
        ['text' => 'The secret of getting ahead is getting started.', 'author' => 'Mark Twain'],
        ['text' => 'Consistency is what transforms average into excellence.', 'author' => 'Unknown'],
        ['text' => 'Every accomplishment starts with the decision to try.', 'author' => 'John F. Kennedy'],
        ['text' => 'A goal without a plan is just a wish.', 'author' => 'Antoine de Saint-Exupery'],
        ['text' => 'Do something today that your future self will thank you for.', 'author' => 'Unknown'],
        ['text' => "Hard work beats talent when talent doesn't work hard.", 'author' => 'Tim Notke'],
    ];

    public static function current(): array
    {
        // Cache key encodes the hour bucket itself, so the quote is
        // guaranteed to change at the top of every clock hour — unlike a
        // rolling now()->addHour() TTL (which only refreshes an hour after
        // whoever happened to load the dashboard first).
        $hourKey = now()->format('Y-m-d-H');

        return Cache::remember("motivation_quote:{$hourKey}", now()->endOfHour(), function () {
            $apiUrl = Setting::get('motivation_quote_api_url');

            if ($apiUrl) {
                $fetched = self::fetchFromApi($apiUrl);

                if ($fetched) {
                    return $fetched;
                }
            }

            return self::QUOTES[now()->hour % count(self::QUOTES)];
        });
    }

    private static function fetchFromApi(string $apiUrl): ?array
    {
        try {
            $response = Http::timeout(4)->get($apiUrl);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $text = $data['content'] ?? $data['quote'] ?? $data[0]['q'] ?? null;

            if (! $text) {
                return null;
            }

            return [
                'text' => $text,
                'author' => $data['author'] ?? $data[0]['a'] ?? 'Unknown',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
