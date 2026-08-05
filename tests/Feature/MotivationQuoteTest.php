<?php

namespace Tests\Feature;

use App\Support\MotivationQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MotivationQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_local_quote_when_no_api_url_is_configured(): void
    {
        $quote = MotivationQuote::current();

        $this->assertArrayHasKey('text', $quote);
        $this->assertArrayHasKey('author', $quote);
        $this->assertNotSame('', trim($quote['text']));
    }

    public function test_the_quote_varies_across_repeated_calls_instead_of_being_cached(): void
    {
        $seen = [];

        for ($i = 0; $i < 20; $i++) {
            $seen[MotivationQuote::current()['text']] = true;
        }

        // With 24 quotes in the pool, 20 random draws landing on the exact
        // same one every time is astronomically unlikely — this would only
        // fail if current() were cached/pinned again (the old hourly-cache
        // behavior this replaces).
        $this->assertGreaterThan(1, count($seen));
    }

    public function test_it_falls_back_to_a_local_quote_when_the_configured_api_fails(): void
    {
        \App\Models\Setting::set('motivation_quote_api_url', 'https://example.test/random-quote');
        Http::fake(['example.test/*' => Http::response(null, 500)]);

        $quote = MotivationQuote::current();

        $this->assertArrayHasKey('text', $quote);
        $this->assertNotSame('', trim($quote['text']));
    }

    public function test_it_uses_the_configured_api_when_it_succeeds(): void
    {
        \App\Models\Setting::set('motivation_quote_api_url', 'https://example.test/random-quote');
        Http::fake(['example.test/*' => Http::response(['content' => 'Custom quote', 'author' => 'Someone'])]);

        $quote = MotivationQuote::current();

        $this->assertSame('Custom quote', $quote['text']);
        $this->assertSame('Someone', $quote['author']);
    }
}
