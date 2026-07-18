<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAcknowledgmentEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_user_can_view_and_acknowledge_a_document(): void
    {
        $user = User::factory()->create();
        $document = PolicyDocument::factory()->create();
        $version = $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0')
            ->postJson(route('policy-documents.view', $version))
            ->assertOk();

        $this->actingAs($user)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0')
            ->postJson(route('policy-documents.acknowledge', $version))
            ->assertOk();

        $this->assertDatabaseHas('policy_document_acknowledgments', [
            'policy_document_version_id' => $version->id,
            'user_id' => $user->id,
            'browser' => 'Chrome',
        ]);

        $acknowledgment = $version->acknowledgments()->where('user_id', $user->id)->first();
        $this->assertNotNull($acknowledgment->viewed_at);
        $this->assertNotNull($acknowledgment->acknowledged_at);
        $this->assertNotNull($acknowledgment->ip_address);
    }

    public function test_unassigned_user_cannot_view_or_acknowledge_a_document(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $document = PolicyDocument::factory()->individualJd()->create(['user_id' => $otherUser->id]);
        $version = $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $otherUser->id,
        ]);

        $this->actingAs($user)->postJson(route('policy-documents.view', $version))->assertForbidden();
        $this->actingAs($user)->postJson(route('policy-documents.acknowledge', $version))->assertForbidden();
    }
}
