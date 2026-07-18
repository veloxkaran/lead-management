<?php

namespace Tests\Feature;

use App\Enums\PolicyDocumentType;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\PolicyDocument;
use App\Models\PolicyDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyDocumentReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_summarizes_company_wide_assigned_counts(): void
    {
        $company = Company::factory()->create();
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'company_id' => $company->id]);
        $acknowledgedUser = User::factory()->create(['company_id' => $company->id]);
        $pendingUser = User::factory()->create(['company_id' => $company->id]);

        $document = PolicyDocument::factory()->create(['company_id' => $company->id, 'type' => PolicyDocumentType::Sop]);
        $version = PolicyDocumentVersion::factory()->create(['policy_document_id' => $document->id]);
        $version->acknowledgments()->create(['user_id' => $acknowledgedUser->id, 'viewed_at' => now(), 'acknowledged_at' => now()]);

        $response = $this->actingAs($superAdmin)->get(route('policy-documents.reports.index'));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) use ($document) {
            $row = $rows->firstWhere('document.id', $document->id);

            // superAdmin + acknowledgedUser + pendingUser — every active user
            // in the company, since Sops are company-wide.
            return $row->assigned_count === 3
                && $row->acknowledged_count === 1
                && $row->pending_count === 2;
        });
    }

    public function test_show_lists_assigned_users_with_their_acknowledgment_status(): void
    {
        $company = Company::factory()->create();
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'company_id' => $company->id]);
        $acknowledgedUser = User::factory()->create(['company_id' => $company->id]);
        $pendingUser = User::factory()->create(['company_id' => $company->id]);

        $document = PolicyDocument::factory()->create(['company_id' => $company->id, 'type' => PolicyDocumentType::Sop]);
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
