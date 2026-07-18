<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAcknowledgmentThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_shows_on_first_load_then_is_throttled_for_12_hours(): void
    {
        $user = User::factory()->create();
        $document = PolicyDocument::factory()->create(['title' => 'Fire Safety SOP']);
        $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Fire Safety SOP');

        $user->refresh();
        $this->assertNotNull($user->policy_ack_last_prompted_at);

        // Immediately reload — same fingerprint, well within 12h — should be suppressed.
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Fire Safety SOP');

        // 13 hours later — throttle window has passed — shown again.
        $this->travel(13)->hours();
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Fire Safety SOP');
    }

    public function test_a_newly_published_version_bypasses_the_throttle_immediately(): void
    {
        $user = User::factory()->create();
        $document = PolicyDocument::factory()->create(['title' => 'Fire Safety SOP']);
        $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Fire Safety SOP');
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Fire Safety SOP');

        // A brand new version appears well within the 12h throttle window.
        $document->versions()->create([
            'version' => '2.0', 'content' => '<p>Updated</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Fire Safety SOP');
    }

    public function test_modal_does_not_render_when_nothing_is_pending(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('policy-ack-overlay');
    }
}
