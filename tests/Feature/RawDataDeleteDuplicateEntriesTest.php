<?php

namespace Tests\Feature;

use App\Enums\RawDataStatus;
use App\Models\Lead;
use App\Models\RawData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataDeleteDuplicateEntriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_entries_sharing_a_phone_are_deduped_keeping_the_earliest(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $first = RawData::factory()->create(['phone' => '9800000000', 'email' => null]);
        $second = RawData::factory()->create(['phone' => '9800000000', 'email' => null]);
        $third = RawData::factory()->create(['phone' => '9800000000', 'email' => null]);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelExists($first);
        $this->assertModelMissing($second);
        $this->assertModelMissing($third);
    }

    public function test_phone_matching_normalizes_formatting_differences(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $first = RawData::factory()->create(['phone' => '(555) 123-4567', 'email' => null]);
        $second = RawData::factory()->create(['phone' => '555-123-4567', 'email' => null]);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelExists($first);
        $this->assertModelMissing($second);
    }

    public function test_entries_with_different_phones_are_not_deduped(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $a = RawData::factory()->create(['phone' => '9111111111', 'email' => null]);
        $b = RawData::factory()->create(['phone' => '9222222222', 'email' => null]);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelExists($a);
        $this->assertModelExists($b);
    }

    public function test_entries_with_no_phone_are_deduped_by_email_case_insensitively(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $first = RawData::factory()->create(['phone' => null, 'email' => 'contact@acme.com']);
        $second = RawData::factory()->create(['phone' => null, 'email' => 'Contact@Acme.com']);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelExists($first);
        $this->assertModelMissing($second);
    }

    public function test_entries_that_have_a_phone_are_never_deduped_by_email(): void
    {
        // Different phones, same email — phone "wins" per spec, so these are
        // left alone even though their emails collide.
        $superAdmin = User::factory()->superAdmin()->create();
        $a = RawData::factory()->create(['phone' => '9333333333', 'email' => 'shared@example.test']);
        $b = RawData::factory()->create(['phone' => '9444444444', 'email' => 'shared@example.test']);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelExists($a);
        $this->assertModelExists($b);
    }

    public function test_an_entry_already_converted_to_a_lead_is_kept_over_an_unconverted_duplicate(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create();
        $converted = RawData::factory()->create([
            'phone' => '9555555555',
            'email' => null,
            'status' => RawDataStatus::ConvertedToLead,
            'converted_lead_id' => $lead->id,
        ]);
        $duplicate = RawData::factory()->create(['phone' => '9555555555', 'email' => null]);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelExists($converted);
        $this->assertModelMissing($duplicate);
    }

    public function test_all_three_cleanup_steps_run_together_and_are_reported(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create(['phone' => '9666666666']);

        $incomplete = RawData::factory()->create(['contact_person' => null, 'phone' => null, 'email' => null]);
        $duplicateOfLead = RawData::factory()->create(['phone' => '9666666666', 'email' => null]);
        $firstInternalDup = RawData::factory()->create(['phone' => '9777777777', 'email' => null]);
        $secondInternalDup = RawData::factory()->create(['phone' => '9777777777', 'email' => null]);
        $keeper = RawData::factory()->create(['phone' => '9888888888', 'email' => 'keeper@example.test']);

        $response = $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $response->assertSessionHas('success', fn ($message) => str_contains($message, '1 incomplete')
            && str_contains($message, 'duplicate(s) of an existing lead')
            && str_contains($message, 'duplicate(s) of another raw data entry'));

        $this->assertModelMissing($incomplete);
        $this->assertModelMissing($duplicateOfLead);
        $this->assertModelExists($firstInternalDup);
        $this->assertModelMissing($secondInternalDup);
        $this->assertModelExists($keeper);
    }

    public function test_a_non_super_admin_cannot_trigger_internal_duplicate_deletion(): void
    {
        $user = User::factory()->create();
        $a = RawData::factory()->create(['phone' => '9999999999', 'email' => null]);
        $b = RawData::factory()->create(['phone' => '9999999999', 'email' => null]);

        $this->actingAs($user)->post(route('raw-data.delete-incomplete'))->assertForbidden();

        $this->assertModelExists($a);
        $this->assertModelExists($b);
    }
}
