<?php

namespace Tests\Feature;

use App\Jobs\PostSlackWriteActivity;
use App\Models\ActivityLogEntry;
use App\Models\Industry;
use App\Models\Setting;
use App\Models\User;
use App\Support\SlackWriteActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SlackWriteActivityTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.test/services/T/B/x';

    private int $slackStatus = 200;

    private int $seen = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.slack.activity_webhook_url' => self::WEBHOOK]);
        Queue::fake([PostSlackWriteActivity::class]);
        Http::fake(fn () => Http::response('ok', $this->slackStatus));
    }

    /** Sends whatever is buffered, then forgets it, so a test starts clean. */
    private function discardPending(): void
    {
        app(SlackWriteActivity::class)->flush();
        $this->seen = Http::recorded()->count();
    }

    /** @return list<string> */
    private function postedTexts(): array
    {
        app(SlackWriteActivity::class)->flush();

        return Http::recorded()->slice($this->seen)->map(fn (array $pair) => $pair[0]['text'])->values()->all();
    }

    public function test_create_update_and_delete_are_posted_as_one_message(): void
    {
        $user = User::factory()->create(['name' => 'Asha Rai']);
        $this->discardPending();
        $this->actingAs($user);

        $industry = Industry::factory()->create(['name' => 'Fintech']);
        $industry->update(['name' => 'Banking']);
        $industry->delete();

        $texts = $this->postedTexts();

        $this->assertCount(1, $texts);
        $this->assertStringContainsString('*Asha Rai*', $texts[0]);
        $this->assertStringContainsString("created Industry #{$industry->id} \"Fintech\"", $texts[0]);
        $this->assertStringContainsString('name: Fintech → Banking', $texts[0]);
        $this->assertStringContainsString("deleted Industry #{$industry->id}", $texts[0]);
        Queue::assertNotPushed(PostSlackWriteActivity::class);
    }

    public function test_a_web_request_posts_its_writes_without_a_queue_worker(): void
    {
        $user = User::factory()->create();
        $this->discardPending();

        // Any write; flush happens in the app's terminating callback.
        Industry::factory()->create(['name' => 'Logistics']);
        $this->app->terminate();

        Http::assertSent(fn (Request $request) => $request->url() === self::WEBHOOK
            && str_contains($request['text'], '"Logistics"'));
    }

    public function test_failed_post_is_queued_for_retry(): void
    {
        $this->slackStatus = 429;

        Industry::factory()->create(['name' => 'Retail']);
        app(SlackWriteActivity::class)->flush();

        Queue::assertPushed(PostSlackWriteActivity::class, fn ($job) => str_contains($job->text, '"Retail"'));
    }

    public function test_sensitive_values_are_redacted(): void
    {
        $user = User::factory()->create();
        $this->discardPending();

        $user->update(['password' => 'super-secret-new-password']);
        Setting::create(['key' => 'campaign_smtp_password', 'value' => 'hunter2']);
        Setting::query()->where('key', 'campaign_smtp_password')->first()->update(['value' => 'hunter3']);

        $text = implode("\n", $this->postedTexts());

        $this->assertStringContainsString('password: [redacted]', $text);
        $this->assertStringContainsString('value: [redacted]', $text);
        $this->assertStringNotContainsString('super-secret-new-password', $text);
        $this->assertStringNotContainsString('hunter3', $text);
    }

    public function test_excluded_models_and_bookkeeping_only_updates_are_skipped(): void
    {
        $user = User::factory()->create();
        $this->discardPending();

        ActivityLogEntry::factory()->create(['user_id' => $user->id]);
        Setting::create(['key' => 'mailbox_sync.system:x@example.test', 'value' => '{}']);
        $user->update(['remember_token' => 'abc']);

        $this->assertSame([], $this->postedTexts());
    }

    public function test_rolled_back_writes_are_not_reported(): void
    {
        DB::beginTransaction();
        Industry::factory()->create(['name' => 'Never happened']);
        DB::rollBack();

        $this->assertSame([], $this->postedTexts());
    }

    public function test_nothing_is_posted_when_webhook_is_blank(): void
    {
        config(['services.slack.activity_webhook_url' => null]);

        Industry::factory()->create();

        $this->assertSame([], $this->postedTexts());
        Queue::assertNotPushed(PostSlackWriteActivity::class);
    }

    public function test_large_batches_are_capped(): void
    {
        Industry::factory()->count(30)->create();

        $text = implode("\n", $this->postedTexts());

        $this->assertSame(25, substr_count($text, 'created Industry'));
        $this->assertStringContainsString('…and 5 more write(s)', $text);
    }

    public function test_queued_retry_job_posts_text_to_the_webhook(): void
    {
        (new PostSlackWriteActivity('hello'))->handle();

        Http::assertSent(fn (Request $request) => $request->url() === self::WEBHOOK && $request['text'] === 'hello');
    }
}
