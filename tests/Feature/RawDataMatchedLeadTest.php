<?php

namespace Tests\Feature;

use App\Enums\RawDataStatus;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\RawData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataMatchedLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_raw_data_entry_sharing_a_phone_with_a_lead_shows_that_leads_status(): void
    {
        $user = User::factory()->create();
        $status = LeadStatus::factory()->create(['name' => 'Negotiating', 'color' => '#ff8800']);
        $lead = Lead::factory()->create(['phone' => '(555) 123-4567', 'lead_status_id' => $status->id]);
        $entry = RawData::factory()->create(['phone' => '555-123-4567', 'email' => null]);

        $response = $this->actingAs($user)->get(route('raw-data.index'));

        $response->assertOk();
        $response->assertSee('Negotiating');

        $matched = $response->viewData('entries')->firstWhere('id', $entry->id)->matched_lead;
        $this->assertNotNull($matched);
        $this->assertSame($lead->id, $matched->id);
    }

    public function test_a_raw_data_entry_sharing_an_email_with_a_lead_matches_case_insensitively(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['email' => 'Contact@Acme.com', 'phone' => '9111111111']);
        $entry = RawData::factory()->create(['phone' => '9222222222', 'email' => 'contact@acme.com']);

        $response = $this->actingAs($user)->get(route('raw-data.index'));

        $matched = $response->viewData('entries')->firstWhere('id', $entry->id)->matched_lead;
        $this->assertNotNull($matched);
        $this->assertSame($lead->id, $matched->id);
    }

    public function test_a_raw_data_entry_with_no_matching_lead_shows_no_match(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['phone' => '9333333333', 'email' => 'someone@else.com']);
        $entry = RawData::factory()->create(['phone' => '9999999999', 'email' => 'nobody@nowhere.com']);

        $response = $this->actingAs($user)->get(route('raw-data.index'));

        $matched = $response->viewData('entries')->firstWhere('id', $entry->id)->matched_lead;
        $this->assertNull($matched);
    }

    public function test_an_entry_already_converted_to_its_own_lead_is_not_flagged_as_matched(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['phone' => '9444444444']);
        $entry = RawData::factory()->create([
            'phone' => '9444444444',
            'status' => RawDataStatus::ConvertedToLead,
            'converted_lead_id' => $lead->id,
        ]);

        $response = $this->actingAs($user)->get(route('raw-data.index'));

        $matched = $response->viewData('entries')->firstWhere('id', $entry->id)->matched_lead;
        $this->assertNull($matched);
    }
}
