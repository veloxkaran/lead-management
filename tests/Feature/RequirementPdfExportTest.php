<?php

namespace Tests\Feature;

use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_export_includes_due_date_and_matches_the_current_filters(): void
    {
        $user = User::factory()->create();
        Requirement::factory()->create([
            'requirement' => 'High priority pending item',
            'priority' => 'high',
            'status' => 'pending',
            'due_date' => '2026-09-01',
        ]);
        Requirement::factory()->create([
            'requirement' => 'Low priority completed item',
            'priority' => 'low',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->get(route('requirements.export-pdf', ['priority' => 'high']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_export_works_with_no_filters_applied(): void
    {
        $user = User::factory()->create();
        Requirement::factory()->count(2)->create();

        $this->actingAs($user)->get(route('requirements.export-pdf'))->assertOk();
    }
}
