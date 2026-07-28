<?php

namespace Tests\Feature;

use App\Enums\RawDataStatus;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\RawData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_raw_data_entry(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('raw-data.store'), [
            'contact_person' => 'Jane Doe',
            'phone' => '9800000000',
        ])->assertRedirect();

        $this->assertDatabaseHas('raw_data', [
            'contact_person' => 'Jane Doe',
            'phone' => '9800000000',
            'status' => RawDataStatus::New->value,
            'created_by' => $user->id,
        ]);
    }

    public function test_duplicate_contact_person_is_rejected(): void
    {
        $user = User::factory()->create();
        RawData::factory()->create(['contact_person' => 'Jane Doe']);

        $response = $this->actingAs($user)->post(route('raw-data.store'), [
            'contact_person' => 'Jane Doe',
            'phone' => '9811111111',
        ]);

        $response->assertSessionHasErrors('contact_person');
        $this->assertSame(1, RawData::where('contact_person', 'Jane Doe')->count());
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $user = User::factory()->create();
        RawData::factory()->create(['phone' => '9800000000']);

        $response = $this->actingAs($user)->post(route('raw-data.store'), [
            'contact_person' => 'Someone Else',
            'phone' => '9800000000',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertSame(1, RawData::where('phone', '9800000000')->count());
    }

    public function test_index_page_lists_entries_with_status(): void
    {
        $user = User::factory()->create();
        $entry = RawData::factory()->create(['contact_person' => 'Jane Doe']);

        $response = $this->actingAs($user)->get(route('raw-data.index'));

        $response->assertOk();
        $response->assertSee('Jane Doe');
    }

    public function test_a_user_can_post_a_comment(): void
    {
        $user = User::factory()->create();
        $entry = RawData::factory()->create();

        $this->actingAs($user)->post(route('raw-data.comments.store', $entry), [
            'comment' => 'Called, no answer.',
        ])->assertRedirect();

        $this->assertDatabaseHas('raw_data_comments', [
            'raw_data_id' => $entry->id,
            'author_id' => $user->id,
            'comment' => 'Called, no answer.',
        ]);
    }

    public function test_entry_can_be_marked_not_valid(): void
    {
        $user = User::factory()->create();
        $entry = RawData::factory()->create();

        $this->actingAs($user)->post(route('raw-data.mark-not-valid', $entry))->assertRedirect();

        $this->assertSame(RawDataStatus::NotValid, $entry->fresh()->status);
    }

    public function test_a_non_new_entry_cannot_be_marked_not_valid_again(): void
    {
        $user = User::factory()->create();
        $entry = RawData::factory()->create(['status' => RawDataStatus::NotValid]);

        $this->actingAs($user)->post(route('raw-data.mark-not-valid', $entry));

        $this->assertSame(RawDataStatus::NotValid, $entry->fresh()->status);
    }

    public function test_entry_can_be_converted_to_a_lead(): void
    {
        $user = User::factory()->create();
        $status = LeadStatus::factory()->create(['is_default' => true]);
        $entry = RawData::factory()->create(['contact_person' => 'Jane Doe', 'phone' => '9800000000']);

        $response = $this->actingAs($user)->post(route('raw-data.convert', $entry), [
            'company_name' => 'Acme Corp',
            'contact_person' => 'Jane Doe',
            'phone' => '9800000000',
            'email' => 'jane@acme.test',
        ]);

        $lead = Lead::firstWhere('company_name', 'Acme Corp');

        $this->assertNotNull($lead);
        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame('Jane Doe', $lead->contact_person);
        $this->assertSame($status->id, $lead->lead_status_id);

        $entry->refresh();
        $this->assertSame(RawDataStatus::ConvertedToLead, $entry->status);
        $this->assertSame($lead->id, $entry->converted_lead_id);
    }

    public function test_an_already_converted_entry_cannot_be_converted_again(): void
    {
        $user = User::factory()->create();
        LeadStatus::factory()->create(['is_default' => true]);
        $existingLead = Lead::factory()->create();
        $entry = RawData::factory()->create([
            'status' => RawDataStatus::ConvertedToLead,
            'converted_lead_id' => $existingLead->id,
        ]);

        $this->actingAs($user)->post(route('raw-data.convert', $entry), [
            'company_name' => 'Another Co',
            'contact_person' => $entry->contact_person,
            'phone' => $entry->phone,
        ]);

        $this->assertSame(0, Lead::where('company_name', 'Another Co')->count());
        $this->assertSame($existingLead->id, $entry->fresh()->converted_lead_id);
    }

    public function test_converting_rejects_a_near_duplicate_company_name(): void
    {
        $user = User::factory()->create();
        LeadStatus::factory()->create(['is_default' => true]);
        Lead::factory()->create(['company_name' => 'Acme Corporation']);
        $entry = RawData::factory()->create();

        $response = $this->actingAs($user)->post(route('raw-data.convert', $entry), [
            'company_name' => 'Acme Corporation',
            'contact_person' => $entry->contact_person,
            'phone' => $entry->phone,
        ]);

        $response->assertSessionHasErrors('company_name');
        $this->assertSame(RawDataStatus::New, $entry->fresh()->status);
    }
}
