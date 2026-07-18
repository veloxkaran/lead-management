<?php

namespace Tests\Feature;

use App\Enums\PolicyDocumentType;
use App\Models\PolicyDocument;
use App\Models\PolicyDocumentAcknowledgment;
use App\Models\User;
use App\Services\PolicyAcknowledgmentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAcknowledgmentResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_documents_are_ordered_sop_then_individual_jd(): void
    {
        $user = User::factory()->create();

        $individualJd = PolicyDocument::factory()->individualJd()->create(['user_id' => $user->id]);
        $sop = PolicyDocument::factory()->create();

        foreach ([$individualJd, $sop] as $document) {
            $document->versions()->create([
                'version' => '1.0',
                'content' => '<p>Body</p>',
                'effective_date' => now()->toDateString(),
                'published_at' => now(),
                'created_by' => $user->id,
            ]);
        }

        $pending = app(PolicyAcknowledgmentResolver::class)->pendingFor($user->fresh());

        $this->assertSame(
            [PolicyDocumentType::Sop, PolicyDocumentType::IndividualJd],
            $pending->map(fn (PolicyDocument $document) => $document->type)->all()
        );
    }

    public function test_a_document_already_acknowledged_at_its_current_version_is_excluded(): void
    {
        $user = User::factory()->create();
        $document = PolicyDocument::factory()->create();
        $version = $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        PolicyDocumentAcknowledgment::create([
            'policy_document_version_id' => $version->id,
            'user_id' => $user->id,
            'viewed_at' => now(),
            'acknowledged_at' => now(),
        ]);

        $pending = app(PolicyAcknowledgmentResolver::class)->pendingFor($user->fresh());

        $this->assertCount(0, $pending);
    }

    public function test_publishing_a_new_version_makes_an_already_acknowledged_user_pending_again(): void
    {
        $user = User::factory()->create();
        $document = PolicyDocument::factory()->create();
        $firstVersion = $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        PolicyDocumentAcknowledgment::create([
            'policy_document_version_id' => $firstVersion->id,
            'user_id' => $user->id,
            'viewed_at' => now(),
            'acknowledged_at' => now(),
        ]);

        $this->assertCount(0, app(PolicyAcknowledgmentResolver::class)->pendingFor($user->fresh()));

        $document->versions()->create([
            'version' => '2.0', 'content' => '<p>Updated body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $pending = app(PolicyAcknowledgmentResolver::class)->pendingFor($user->fresh());

        $this->assertCount(1, $pending);
        $this->assertSame('2.0', $pending->first()->currentVersion->version);
    }

    public function test_a_version_scheduled_in_the_future_is_not_yet_pending(): void
    {
        $user = User::factory()->create();
        $document = PolicyDocument::factory()->create();
        $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>',
            'effective_date' => now()->addWeek()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $pending = app(PolicyAcknowledgmentResolver::class)->pendingFor($user);

        $this->assertCount(0, $pending);
    }
}
