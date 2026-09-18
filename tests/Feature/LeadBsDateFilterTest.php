<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Support\BsDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadBsDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function touchCreatedAt(Lead $lead, $createdAt): void
    {
        // A direct query-builder update, bypassing Eloquent's fillable guard
        // and updateTimestamps() (created_at isn't mass-assignable and would
        // otherwise be silently reset to now() on save).
        Lead::query()->where('id', $lead->id)->update(['created_at' => $createdAt]);
    }

    public function test_bs_year_and_month_filters_leads_created_within_that_bs_month(): void
    {
        $user = User::factory()->create();

        [$from, $to] = BsDate::monthToAdRange(2082, 6);

        $inRange = Lead::factory()->create(['created_by' => $user->id, 'company_name' => 'In Range Co']);
        $this->touchCreatedAt($inRange, $from->copy()->addDay());

        $beforeRange = Lead::factory()->create(['created_by' => $user->id, 'company_name' => 'Before Range Co']);
        $this->touchCreatedAt($beforeRange, $from->copy()->subDay());

        $afterRange = Lead::factory()->create(['created_by' => $user->id, 'company_name' => 'After Range Co']);
        $this->touchCreatedAt($afterRange, $to->copy()->addDay());

        $response = $this->actingAs($user)->get(route('leads.index', [
            'created_by' => '',
            'bs_year' => 2082,
            'bs_month' => 6,
        ]));

        $response->assertOk();
        $response->assertSee('In Range Co');
        $response->assertDontSee('Before Range Co');
        $response->assertDontSee('After Range Co');
    }

    public function test_bs_filter_is_ignored_when_only_year_or_only_month_is_given(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['created_by' => $user->id, 'company_name' => 'Some Lead Co']);

        $response = $this->actingAs($user)->get(route('leads.index', [
            'created_by' => '',
            'bs_year' => 2082,
        ]));

        $response->assertOk();
        $response->assertSee('Some Lead Co');
    }

    public function test_index_offers_bs_year_and_month_options(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('leads.index'));

        $response->assertOk();
        $response->assertSee('Created (BS Year)');
        $response->assertSee('Created (BS Month)');
        $response->assertSee(BsDate::currentYear());
        $response->assertSee('Baisakh');
    }
}
