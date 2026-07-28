<?php

namespace Tests\Feature;

use App\Support\MotivationQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MotivationQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_local_quote_when_no_api_url_is_configured(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 28, 9, 15));

        $quote = MotivationQuote::current();

        $this->assertArrayHasKey('text', $quote);
        $this->assertArrayHasKey('author', $quote);
        $this->assertNotSame('', trim($quote['text']));
    }

    public function test_the_quote_changes_when_the_hour_changes(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 28, 9, 15));
        $first = MotivationQuote::current();

        $this->travelTo(Carbon::create(2026, 7, 28, 10, 15));
        $second = MotivationQuote::current();

        $this->assertNotSame($first['text'], $second['text']);
    }

    public function test_the_quote_stays_the_same_within_the_same_hour(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 28, 9, 5));
        $first = MotivationQuote::current();

        $this->travelTo(Carbon::create(2026, 7, 28, 9, 55));
        $second = MotivationQuote::current();

        $this->assertSame($first, $second);
    }

    public function test_it_falls_back_to_a_local_quote_when_the_configured_api_fails(): void
    {
        \App\Models\Setting::set('motivation_quote_api_url', 'https://example.test/random-quote');
        Http::fake(['example.test/*' => Http::response(null, 500)]);

        $this->travelTo(Carbon::create(2026, 7, 28, 11, 0));

        $quote = MotivationQuote::current();

        $this->assertArrayHasKey('text', $quote);
        $this->assertNotSame('', trim($quote['text']));
    }

    public function test_it_uses_the_configured_api_when_it_succeeds(): void
    {
        \App\Models\Setting::set('motivation_quote_api_url', 'https://example.test/random-quote');
        Http::fake(['example.test/*' => Http::response(['content' => 'Custom quote', 'author' => 'Someone'])]);

        $this->travelTo(Carbon::create(2026, 7, 28, 12, 0));
        Cache::flush();

        $quote = MotivationQuote::current();

        $this->assertSame('Custom quote', $quote['text']);
        $this->assertSame('Someone', $quote['author']);
    }
}
