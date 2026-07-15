<?php

namespace Tests\Feature;

use App\Enums\PolicyDocumentType;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\PolicyDocument;
use App\Models\PolicyDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyDocumentReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_summarizes_department_assigned_counts(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $department = Department::factory()->create();
        $acknowledgedUser = User::factory()->create(['department_id' => $department->id]);
        $pendingUser = User::factory()->create(['department_id' => $department->id]);

        $document = PolicyDocument::factory()->create(['department_id' => $department->id, 'type' => PolicyDocumentType::Sop]);
        $version = PolicyDocumentVersion::factory()->create(['policy_document_id' => $document->id]);
        $version->acknowledgments()->create(['user_id' => $acknowledgedUser->id, 'viewed_at' => now(), 'acknowledged_at' => now()]);

        $response = $this->actingAs($superAdmin)->get(route('policy-documents.reports.index'));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) use ($document) {
            $row = $rows->firstWhere('document.id', $document->id);

            return $row->assigned_count === 2
                && $row->acknowledged_count === 1
                && $row->pending_count === 1;
        });
    }

    public function test_show_lists_assigned_users_with_their_acknowledgment_status(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $department = Department::factory()->create();
        $acknowledgedUser = User::factory()->create(['department_id' => $department->id]);
        $pendingUser = User::factory()->create(['department_id' => $department->id]);

        $document = PolicyDocument::factory()->create(['department_id' => $department->id, 'type' => PolicyDocumentType::Sop]);
        $version = PolicyDocumentVersion::factory()->create(['policy_document_id' => $document->id]);
        $version->acknowledgments()->create(['user_id' => $acknowledgedUser->id, 'viewed_at' => now(), 'acknowledged_at' => now()]);

        $response = $this->actingAs($superAdmin)->get(route('policy-documents.reports.show', $document));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) use ($acknowledgedUser, $pendingUser) {
            $acknowledgedRow = $rows->firstWhere('user.id', $acknowledgedUser->id);
            $pendingRow = $rows->firstWhere('user.id', $pendingUser->id);

            return $acknowledgedRow->acknowledged_at !== null && $pendingRow->acknowledged_at === null;
        });
    }

    public function test_non_super_admin_cannot_view_reports(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $document = PolicyDocument::factory()->create();

        $this->actingAs($manager)->get(route('policy-documents.reports.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('policy-documents.reports.show', $document))->assertForbidden();
    }
}
