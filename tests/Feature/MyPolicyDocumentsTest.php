<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyPolicyDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_assigned_documents_with_status(): void
    {
        $user = User::factory()->create();
        $sop = PolicyDocument::factory()->create(['title' => 'Attendance SOP']);
        $sop->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('my-policy-documents.index'));

        $response->assertOk()
            ->assertSee('Attendance SOP')
            ->assertSee('Unread');
    }

    public function test_employee_can_reopen_and_review_a_document_outside_the_forced_flow(): void
    {
        $user = User::factory()->create();
        $sop = PolicyDocument::factory()->create(['title' => 'Attendance SOP']);
        $version = $sop->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('my-policy-documents.show', $version))
            ->assertOk()
            ->assertSee('Attendance SOP');
    }

    public function test_employee_cannot_reopen_a_document_not_assigned_to_them(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $individualJd = PolicyDocument::factory()->individualJd()->create(['user_id' => $otherUser->id]);
        $version = $individualJd->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $otherUser->id,
        ]);

        $this->actingAs($user)->get(route('my-policy-documents.show', $version))->assertForbidden();
    }
}
