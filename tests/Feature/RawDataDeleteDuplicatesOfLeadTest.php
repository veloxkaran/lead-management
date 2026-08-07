<?php

namespace Tests\Feature;

use App\Enums\RawDataStatus;
use App\Models\Lead;
use App\Models\RawData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataDeleteDuplicatesOfLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_entry_sharing_a_phone_with_a_lead_is_deleted(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['phone' => '(555) 123-4567']);
        $entry = RawData::factory()->create(['phone' => '555-123-4567', 'email' => null]);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelMissing($entry);
    }

    public function test_an_entry_sharing_an_email_with_a_lead_is_deleted_case_insensitively(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'Contact@Acme.com', 'phone' => '9111111111']);
        $entry = RawData::factory()->create(['phone' => '9222222222', 'email' => 'contact@acme.com']);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelMissing($entry);
    }

    public function test_an_entry_with_no_matching_lead_is_kept(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['phone' => '9333333333', 'email' => 'someone@else.com']);
        $entry = RawData::factory()->create(['phone' => '9999999999', 'email' => 'nobody@nowhere.com']);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelExists($entry);
    }

    public function test_an_entry_already_converted_to_its_own_lead_is_not_deleted_as_a_duplicate(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create(['phone' => '9444444444']);
        $entry = RawData::factory()->create([
            'phone' => '9444444444',
            'status' => RawDataStatus::ConvertedToLead,
            'converted_lead_id' => $lead->id,
        ]);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelExists($entry);
    }

    public function test_incomplete_deletion_and_duplicate_deletion_both_run_in_one_request(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create(['phone' => '9555555555']);

        $incomplete = RawData::factory()->create(['contact_person' => null, 'phone' => null, 'email' => null]);
        $duplicate = RawData::factory()->create(['phone' => '9555555555', 'email' => null]);
        $keeper = RawData::factory()->create(['phone' => '9666666666', 'email' => 'keeper@example.test']);

        $response = $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $response->assertSessionHas('success', fn ($message) => str_contains($message, '1 incomplete') && str_contains($message, '1 entry/entries deleted as duplicate'));
        $this->assertModelMissing($incomplete);
        $this->assertModelMissing($duplicate);
        $this->assertModelExists($keeper);
    }

    public function test_a_non_super_admin_cannot_trigger_duplicate_deletion(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['phone' => '9777777777']);
        $entry = RawData::factory()->create(['phone' => '9777777777', 'email' => null]);

        $this->actingAs($user)->post(route('raw-data.delete-incomplete'))->assertForbidden();

        $this->assertModelExists($entry);
    }
}
