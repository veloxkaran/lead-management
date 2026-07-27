<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LeadBulkUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_upload_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('leads.bulk-upload.create'))->assertOk();
    }

    public function test_template_can_be_downloaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('leads.bulk-upload.template'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_valid_rows_are_imported_with_default_status_and_history(): void
    {
        $user = User::factory()->create();
        $status = LeadStatus::factory()->create(['is_default' => true]);

        $csv = "Company Name,Contact Person,Email,Phone,Industry,Source\n"
            ."Acme Test Co,John Doe,john@acme.test,1234567890,Retail,Referral\n";

        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->actingAs($user)->post(route('leads.bulk-upload.store'), ['file' => $file])
            ->assertRedirect(route('leads.bulk-upload.create'));

        $lead = Lead::firstWhere('company_name', 'Acme Test Co');

        $this->assertNotNull($lead);
        $this->assertSame('John Doe', $lead->contact_person);
        $this->assertSame($user->id, $lead->created_by);
        $this->assertSame($status->id, $lead->lead_status_id);
        $this->assertDatabaseHas('lead_status_histories', [
            'lead_id' => $lead->id,
            'to_status_id' => $status->id,
        ]);
    }

    public function test_rows_missing_required_fields_are_skipped_and_reported(): void
    {
        $user = User::factory()->create();
        LeadStatus::factory()->create(['is_default' => true]);

        $csv = "Company Name,Contact Person,Email,Phone,Industry,Source\n"
            ."Good Co,Jane Doe,jane@good.test,,,\n"
            .",Missing Company Name,x@x.test,,,\n";

        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $response = $this->actingAs($user)->post(route('leads.bulk-upload.store'), ['file' => $file]);

        $response->assertRedirect(route('leads.bulk-upload.create'));
        $response->assertSessionHas('importFailures', fn ($failures) => count($failures) === 1);

        $this->assertDatabaseHas('leads', ['company_name' => 'Good Co']);
        $this->assertDatabaseMissing('leads', ['contact_person' => 'Missing Company Name']);
        $this->assertSame(1, Lead::count());
    }

    public function test_near_duplicate_company_names_are_rejected(): void
    {
        $user = User::factory()->create();
        LeadStatus::factory()->create(['is_default' => true]);
        Lead::factory()->create(['company_name' => 'Acme Corporation']);

        $csv = "Company Name,Contact Person,Email,Phone,Industry,Source\n"
            ."Acme Corporation,John Doe,,,,\n";

        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $response = $this->actingAs($user)->post(route('leads.bulk-upload.store'), ['file' => $file]);

        $response->assertSessionHas('importFailures', fn ($failures) => count($failures) === 1);
        $this->assertSame(1, Lead::where('company_name', 'Acme Corporation')->count());
    }
}
