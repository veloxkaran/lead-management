<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyPolicyDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_assigned_documents_with_status(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);
        $sop = PolicyDocument::factory()->create(['department_id' => $department->id, 'title' => 'Attendance SOP']);
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
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);
        $sop = PolicyDocument::factory()->create(['department_id' => $department->id, 'title' => 'Attendance SOP']);
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
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $otherDepartment->id]);
        $sop = PolicyDocument::factory()->create(['department_id' => $department->id]);
        $version = $sop->versions()->create([
            'version' => '1.0', 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
            'published_at' => now(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('my-policy-documents.show', $version))->assertForbidden();
    }
}
