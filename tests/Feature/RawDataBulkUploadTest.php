<?php

namespace Tests\Feature;

use App\Models\RawData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RawDataBulkUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_upload_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('raw-data.bulk-upload.create'))->assertOk();
    }

    public function test_template_can_be_downloaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raw-data.bulk-upload.template'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_valid_rows_are_imported(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone\n"
            ."Jane Doe,9800000000\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file])
            ->assertRedirect(route('raw-data.bulk-upload.create'));

        $entry = RawData::firstWhere('contact_person', 'Jane Doe');

        $this->assertNotNull($entry);
        $this->assertSame('9800000000', $entry->phone);
        $this->assertSame($user->id, $entry->created_by);
    }

    public function test_rows_missing_required_fields_are_skipped_and_reported(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone\n"
            ."Good Contact,9800000001\n"
            .",9800000002\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $response = $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $response->assertSessionHas('importFailures', fn ($failures) => count($failures) === 1);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Good Contact']);
        $this->assertSame(1, RawData::count());
    }

    public function test_a_matching_phone_row_fills_in_missing_email_and_source_instead_of_being_rejected(): void
    {
        $user = User::factory()->create();
        $existing = RawData::factory()->create(['phone' => '9800000000', 'email' => null, 'source' => null]);

        $csv = "Contact Person,Phone,Email,Source\n"
            .'Jane Doe,9800000000,jane@example.test,Referral'."\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $response = $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, RawData::where('phone', '9800000000')->count());

        $existing->refresh();
        $this->assertSame('jane@example.test', $existing->email);
        $this->assertSame('Referral', $existing->source);
    }

    public function test_a_matching_phone_row_never_overwrites_an_already_set_field(): void
    {
        $user = User::factory()->create();
        $existing = RawData::factory()->create(['phone' => '9800000000', 'email' => 'original@example.test', 'source' => null]);

        $csv = "Contact Person,Phone,Email,Source\n"
            .'Jane Doe,9800000000,new@example.test,Referral'."\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $existing->refresh();
        $this->assertSame('original@example.test', $existing->email);
        $this->assertSame('Referral', $existing->source);
    }

    public function test_a_matching_contact_person_row_fills_in_missing_details_when_phone_differs(): void
    {
        $user = User::factory()->create();
        $existing = RawData::factory()->create(['contact_person' => 'Jane Doe', 'phone' => '9800000000', 'source' => null]);

        $csv = "Contact Person,Phone,Source\n"
            .'Jane Doe,9811111111,Referral'."\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $this->assertSame(1, RawData::count());
        $this->assertSame('Referral', $existing->fresh()->source);
    }
}
