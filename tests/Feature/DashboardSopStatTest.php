<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\PolicyDocument;
use App\Models\PolicyDocumentAcknowledgment;
use App\Models\PolicyDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSopStatTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_no_sops_configured_when_none_exist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No SOPs Configured')
            ->assertViewHas('sopStats', fn ($stats) => $stats['active_count'] === 0);
    }

    public function test_shows_a_real_active_count_and_acknowledgment_rate_computed_from_the_database(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'company_id' => $company->id]);
        $acknowledgedUser = User::factory()->create(['company_id' => $company->id, 'status' => UserStatus::Active]);
        $pendingUser = User::factory()->create(['company_id' => $company->id, 'status' => UserStatus::Active]);

        $document = PolicyDocument::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $version = PolicyDocumentVersion::factory()->create(['policy_document_id' => $document->id]);

        PolicyDocumentAcknowledgment::create([
            'policy_document_version_id' => $version->id,
            'user_id' => $acknowledgedUser->id,
            'viewed_at' => now(),
            'acknowledged_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('No SOPs Configured');
        // $admin, $acknowledgedUser, and $pendingUser are all active users
        // in the company, so the Sop applies to all three — 1 of 3
        // acknowledged is 33%, not a fixed/hardcoded figure.
        $response->assertViewHas('sopStats', fn ($stats) => $stats['active_count'] === 1 && $stats['rate'] === 33);
    }
}
