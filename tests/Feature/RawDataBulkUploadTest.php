<?php

namespace Tests\Feature;

use App\Models\RawData;
use App\Models\RawDataImportBatch;
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

        $response = $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $entry = RawData::firstWhere('contact_person', 'Jane Doe');

        $this->assertNotNull($entry);
        $this->assertSame('9800000000', $entry->phone);
        $this->assertSame($user->id, $entry->created_by);

        $batch = RawDataImportBatch::sole();
        $response->assertRedirect(route('raw-data.bulk-upload.batches.show', $batch));
        $this->assertSame(1, $batch->total_rows);
        $this->assertSame(1, $batch->imported_count);
        $this->assertSame(0, $batch->rejected_count);
    }

    public function test_a_file_without_a_contact_person_column_still_imports(): void
    {
        $user = User::factory()->create();

        $csv = "Phone,Email\n"
            ."9800000001,jane@example.test\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(0, $batch->rejected_count);
        $this->assertDatabaseHas('raw_data', ['phone' => '9800000001']);
        $this->assertSame(1, RawData::count());
    }

    public function test_a_file_without_a_phone_column_still_imports(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Email\n"
            ."Jane Doe,jane@example.test\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(0, $batch->rejected_count);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Jane Doe']);
        $this->assertSame(1, RawData::count());
    }

    public function test_rows_with_invalid_data_are_skipped_and_reported(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone,Email\n"
            ."Good Contact,9800000001,good@example.test\n"
            ."Bad Email Contact,9800000002,not-an-email\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->imported_count);
        $this->assertSame(1, $batch->rejected_count);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Good Contact']);
        $this->assertSame(1, RawData::count());

        $rejection = $batch->rejections()->sole();
        $this->assertArrayHasKey('email', $rejection->errors);
        $this->assertSame('Bad Email Contact', $rejection->raw_data['contact_person']);
    }

    public function test_a_row_with_a_blank_contact_person_is_still_imported(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone\n"
            .",9800000001\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(0, $batch->rejected_count);
        $this->assertDatabaseHas('raw_data', ['phone' => '9800000001']);
        $this->assertSame(1, RawData::count());
    }

    public function test_a_row_with_a_blank_phone_is_still_imported(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone\n"
            ."Jane Doe,\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(0, $batch->rejected_count);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Jane Doe']);
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

        $batch = RawDataImportBatch::sole();
        $this->assertSame(1, $batch->updated_count);
        $this->assertSame(0, $batch->rejected_count);
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

    public function test_company_name_and_notes_columns_are_imported_and_fill_in_missing_details(): void
    {
        $user = User::factory()->create();
        $existing = RawData::factory()->create(['phone' => '9800000000', 'company_name' => null, 'notes' => null]);

        $csv = "Contact Person,Phone,Company Name,Notes\n"
            .'Jane Doe,9800000000,Acme Corp,Met at trade show'."\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $existing->refresh();
        $this->assertSame('Acme Corp', $existing->company_name);
        $this->assertSame('Met at trade show', $existing->notes);
    }

    public function test_number_of_employees_column_is_imported_and_fills_in_missing_details(): void
    {
        $user = User::factory()->create();
        $existing = RawData::factory()->create(['phone' => '9800000000', 'number_of_employees' => null]);

        $csv = "Contact Person,Phone,Number of Employees\n"
            .'Jane Doe,9800000000,120'."\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $existing->refresh();
        $this->assertSame(120, $existing->number_of_employees);
    }

    public function test_a_row_matching_an_existing_up_to_date_entry_counts_as_imported_not_rejected(): void
    {
        $user = User::factory()->create();
        RawData::factory()->create([
            'phone' => '9800000000',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@example.test',
            'source' => 'Referral',
        ]);

        $csv = "Contact Person,Phone,Email,Source\n"
            .'Jane Doe,9800000000,jane@example.test,Referral'."\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(0, $batch->rejected_count);
        $this->assertSame(1, $batch->unchanged_count);
        $this->assertSame(1, $batch->successfulCount());
    }

    public function test_import_summary_counts_are_correct_on_a_mixed_file(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone,Email\n"
            ."Good One,9800000001,good1@example.test\n"
            ."Good Two,9800000002,good2@example.test\n"
            ."Bad One,9800000003,not-an-email\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(3, $batch->total_rows);
        $this->assertSame(2, $batch->imported_count);
        $this->assertSame(1, $batch->rejected_count);
        $this->assertSame(2, $batch->successfulCount());
    }

    public function test_batch_results_page_lists_rejected_rows(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone,Email\n"
            ."Bad One,9800000003,not-an-email\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $response = $this->actingAs($user)->get(route('raw-data.bulk-upload.batches.show', $batch));

        $response->assertOk();
        $response->assertSee('Bad One');
    }

    public function test_rejected_rows_can_be_downloaded(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone,Email\n"
            ."Bad One,9800000003,not-an-email\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $batch = RawDataImportBatch::sole();

        $response = $this->actingAs($user)->get(route('raw-data.bulk-upload.batches.download', $batch));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_pasted_valid_rows_are_imported(): void
    {
        $user = User::factory()->create();

        $rows = json_encode([
            ['contact_person' => 'Jane Doe', 'phone' => '9800000000', 'email' => 'jane@example.test', 'source' => 'Referral'],
        ]);

        $response = $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => $rows]);

        $entry = RawData::firstWhere('contact_person', 'Jane Doe');

        $this->assertNotNull($entry);
        $this->assertSame('9800000000', $entry->phone);
        $this->assertSame($user->id, $entry->created_by);

        $batch = RawDataImportBatch::sole();
        $response->assertRedirect(route('raw-data.bulk-upload.batches.show', $batch));
        $this->assertSame('paste', $batch->source);
    }

    public function test_pasted_blank_rows_are_ignored(): void
    {
        $user = User::factory()->create();

        $rows = json_encode([
            ['contact_person' => 'Jane Doe', 'phone' => '9800000000', 'email' => '', 'source' => ''],
            ['contact_person' => '', 'phone' => '', 'email' => '', 'source' => ''],
        ]);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => $rows]);

        $this->assertSame(1, RawData::count());

        $batch = RawDataImportBatch::sole();
        $this->assertSame(1, $batch->total_rows);
    }

    public function test_pasted_rows_with_invalid_data_are_skipped_and_reported(): void
    {
        $user = User::factory()->create();

        $rows = json_encode([
            ['contact_person' => 'Good Contact', 'phone' => '9800000001', 'email' => 'good@example.test', 'source' => ''],
            ['contact_person' => 'Bad Email Contact', 'phone' => '9800000002', 'email' => 'not-an-email', 'source' => ''],
        ]);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => $rows]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(1, $batch->rejected_count);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Good Contact']);
        $this->assertSame(1, RawData::count());
    }

    public function test_a_pasted_row_with_a_blank_contact_person_is_still_imported(): void
    {
        $user = User::factory()->create();

        $rows = json_encode([
            ['contact_person' => '', 'phone' => '9800000001', 'email' => '', 'source' => ''],
        ]);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => $rows]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(0, $batch->rejected_count);
        $this->assertDatabaseHas('raw_data', ['phone' => '9800000001']);
        $this->assertSame(1, RawData::count());
    }

    public function test_a_pasted_row_with_a_blank_phone_is_still_imported(): void
    {
        $user = User::factory()->create();

        $rows = json_encode([
            ['contact_person' => 'Jane Doe', 'phone' => '', 'email' => '', 'source' => ''],
        ]);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => $rows]);

        $batch = RawDataImportBatch::sole();

        $this->assertSame(0, $batch->rejected_count);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Jane Doe']);
        $this->assertSame(1, RawData::count());
    }

    public function test_pasted_row_matching_existing_phone_fills_in_missing_details(): void
    {
        $user = User::factory()->create();
        $existing = RawData::factory()->create(['phone' => '9800000000', 'email' => null, 'source' => null]);

        $rows = json_encode([
            ['contact_person' => 'Jane Doe', 'phone' => '9800000000', 'email' => 'jane@example.test', 'source' => 'Referral'],
        ]);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => $rows]);

        $this->assertSame(1, RawData::where('phone', '9800000000')->count());

        $existing->refresh();
        $this->assertSame('jane@example.test', $existing->email);
        $this->assertSame('Referral', $existing->source);
    }

    public function test_pasted_company_name_and_notes_are_saved_and_fill_in_missing_details(): void
    {
        $user = User::factory()->create();
        $existing = RawData::factory()->create(['phone' => '9800000000', 'company_name' => null, 'notes' => null]);

        $rows = json_encode([
            ['contact_person' => 'Jane Doe', 'phone' => '9800000000', 'company_name' => 'Acme Corp', 'notes' => 'Met at trade show'],
        ]);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => $rows]);

        $existing->refresh();
        $this->assertSame('Acme Corp', $existing->company_name);
        $this->assertSame('Met at trade show', $existing->notes);
    }

    public function test_multiple_rows_with_blank_phone_all_create_distinct_entries(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone\n"
            ."Alice,\n"
            ."Bob,\n"
            ."Carol,\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $this->assertSame(3, RawData::count());
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Alice']);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Bob']);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Carol']);

        $batch = RawDataImportBatch::sole();
        $this->assertSame(3, $batch->imported_count);
    }

    public function test_multiple_rows_with_blank_phone_and_blank_contact_person_all_create_distinct_entries(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone,Company Name\n"
            .",,Acme Corp\n"
            .",,Beta LLC\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $this->assertSame(2, RawData::count());
        $this->assertDatabaseHas('raw_data', ['company_name' => 'Acme Corp']);
        $this->assertDatabaseHas('raw_data', ['company_name' => 'Beta LLC']);
    }

    public function test_multiple_pasted_rows_with_blank_phone_all_create_distinct_entries(): void
    {
        $user = User::factory()->create();

        $rows = json_encode([
            ['contact_person' => 'Alice', 'phone' => ''],
            ['contact_person' => 'Bob', 'phone' => ''],
        ]);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => $rows]);

        $this->assertSame(2, RawData::count());
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Alice']);
        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Bob']);
    }

    public function test_recently_imported_records_are_immediately_visible_in_the_list(): void
    {
        $user = User::factory()->create();

        $csv = "Contact Person,Phone\n"
            ."Alice,\n"
            ."Bob,\n"
            ."Carol,9800000009\n";

        $file = UploadedFile::fake()->createWithContent('raw-data.csv', $csv);

        $this->actingAs($user)->post(route('raw-data.bulk-upload.store'), ['file' => $file]);

        $response = $this->actingAs($user)->get(route('raw-data.index'));

        $response->assertOk();
        $response->assertSee('Alice');
        $response->assertSee('Bob');
        $response->assertSee('Carol');
        $this->assertSame(3, $response->viewData('entries')->total());
    }

    public function test_pasted_rows_are_rejected_when_malformed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('raw-data.bulk-upload.store-paste'), ['rows' => 'not-json']);

        $response->assertSessionHas('error');
        $this->assertSame(0, RawData::count());
        $this->assertSame(0, RawDataImportBatch::count());
    }
}
