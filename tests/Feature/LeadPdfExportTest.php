<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AccountRequest;
use App\Models\ImplementationRequest;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_download_the_full_history_pdf(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp']);

        Requirement::factory()->create(['lead_id' => $lead->id]);
        $ticket = SupportTicket::factory()->create(['lead_id' => $lead->id]);
        $ticket->comments()->create(['comment' => 'Working on it', 'author_id' => $superAdmin->id]);
        Task::factory()->create(['lead_id' => $lead->id]);
        ImplementationRequest::factory()->create(['lead_id' => $lead->id]);
        Training::factory()->create(['lead_id' => $lead->id]);
        AccountRequest::factory()->create(['lead_id' => $lead->id]);

        $response = $this->actingAs($superAdmin)->get(route('leads.export-pdf', $lead));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_non_super_admin_cannot_download_the_full_history_pdf(): void
    {
        $user = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('leads.export-pdf', $lead))->assertForbidden();
    }
}
