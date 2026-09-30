<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\RawData;
use App\Models\User;
use App\Support\SimilarLeadFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataSimilarLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_finder_matches_names_at_least_half_similar_best_first(): void
    {
        $exact = Lead::factory()->create(['company_name' => 'Himalayan Tech']);
        $close = Lead::factory()->create(['company_name' => 'Himalaya Technologies']);
        Lead::factory()->create(['company_name' => 'Zebra Foods']);

        $matches = app(SimilarLeadFinder::class)->find('  himalayan   TECH ');

        $this->assertSame([$exact->id, $close->id], $matches->pluck('lead.id')->all());
        $this->assertSame(100, $matches[0]['similarity']);
        $this->assertGreaterThanOrEqual(50, $matches[1]['similarity']);
    }

    public function test_finder_ignores_blank_and_very_short_names(): void
    {
        Lead::factory()->create(['company_name' => 'AB']);

        $this->assertCount(0, app(SimilarLeadFinder::class)->find(null));
        $this->assertCount(0, app(SimilarLeadFinder::class)->find('AB'));
    }

    public function test_saving_a_similar_company_name_is_held_back_and_lists_the_leads(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['company_name' => 'Himalayan Tech', 'contact_person' => 'Sita Sharma']);

        $this->actingAs($user)->post(route('raw-data.store'), [
            'contact_person' => 'Ram Thapa',
            'company_name' => 'Himalayan Tek',
        ])->assertSessionHasErrors('company_name');

        $this->assertSame(0, RawData::count());

        $this->actingAs($user)->withSession(['_old_input' => ['company_name' => 'Himalayan Tek']])
            ->get(route('raw-data.create'))
            ->assertOk()
            ->assertSee('Himalayan Tech')
            ->assertSee('Sita Sharma')
            ->assertSee('Save anyway');
    }

    public function test_save_anyway_creates_the_entry_despite_similar_leads(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['company_name' => 'Himalayan Tech']);

        $this->actingAs($user)->post(route('raw-data.store'), [
            'contact_person' => 'Ram Thapa',
            'company_name' => 'Himalayan Tek',
            'confirm_similar_leads' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('raw_data', ['contact_person' => 'Ram Thapa', 'company_name' => 'Himalayan Tek']);
    }

    public function test_a_clearly_different_or_missing_company_name_saves_normally(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['company_name' => 'Himalayan Tech']);

        $this->actingAs($user)->post(route('raw-data.store'), [
            'contact_person' => 'Ram Thapa',
            'company_name' => 'Zebra Foods',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('raw-data.store'), [
            'contact_person' => 'Gita Rai',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, RawData::count());
    }

    public function test_live_lookup_returns_similar_leads(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Himalayan Tech']);
        Lead::factory()->create(['company_name' => 'Zebra Foods']);

        $this->actingAs($user)->getJson(route('raw-data.similar-leads', ['company_name' => 'Himalayan']))
            ->assertOk()
            ->assertJsonCount(1, 'matches')
            ->assertJsonPath('matches.0.id', $lead->id)
            ->assertJsonPath('matches.0.url', route('leads.show', $lead));
    }

    public function test_entry_page_flags_similar_leads_until_converted(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Himalayan Tech']);
        $entry = RawData::factory()->create(['company_name' => 'Himalayan Tek']);

        $this->actingAs($user)->get(route('raw-data.show', $entry))
            ->assertOk()
            ->assertSee('Similar existing leads')
            ->assertSee(route('leads.show', $lead));

        $entry->update(['converted_lead_id' => $lead->id]);

        $this->actingAs($user)->get(route('raw-data.show', $entry))
            ->assertOk()
            ->assertDontSee('Similar existing leads');
    }
}
