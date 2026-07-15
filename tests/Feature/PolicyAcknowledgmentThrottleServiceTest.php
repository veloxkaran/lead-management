<?php

namespace Tests\Feature;

use App\Enums\PolicyDocumentType;
use App\Models\Department;
use App\Models\PolicyDocument;
use App\Models\User;
use App\Services\PolicyAcknowledgmentThrottleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAcknowledgmentThrottleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_when_nothing_is_pending(): void
    {
        $user = User::factory()->create();

        $result = app(PolicyAcknowledgmentThrottleService::class)->resolveForRequest($user);

        $this->assertTrue($result->isEmpty());
        $this->assertNull($user->fresh()->policy_ack_last_prompted_at);
    }

    public function test_prompts_and_persists_fingerprint_on_first_call(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);
        $document = PolicyDocument::factory()->create(['department_id' => $department->id, 'type' => PolicyDocumentType::Sop]);
        $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $result = app(PolicyAcknowledgmentThrottleService::class)->resolveForRequest($user);

        $this->assertCount(1, $result);
        $user->refresh();
        $this->assertNotNull($user->policy_ack_last_prompted_at);
        $this->assertNotNull($user->policy_ack_last_prompted_fingerprint);
    }

    public function test_second_call_within_12_hours_with_same_fingerprint_is_throttled(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);
        $document = PolicyDocument::factory()->create(['department_id' => $department->id, 'type' => PolicyDocumentType::Sop]);
        $document->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $service = app(PolicyAcknowledgmentThrottleService::class);
        $service->resolveForRequest($user);

        $result = $service->resolveForRequest($user->fresh());

        $this->assertTrue($result->isEmpty());
    }
}
