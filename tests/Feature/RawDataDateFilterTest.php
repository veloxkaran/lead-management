<?php

namespace Tests\Feature;

use App\Models\RawData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function touchCreatedAt(RawData $rawData, $createdAt): void
    {
        // A direct query-builder update, bypassing Eloquent's fillable guard
        // and updateTimestamps() (created_at isn't mass-assignable and would
        // otherwise be silently reset to now() on save).
        RawData::query()->where('id', $rawData->id)->update(['created_at' => $createdAt]);
    }

    public function test_period_today_only_shows_entries_created_today(): void
    {
        $user = User::factory()->create();

        $today = RawData::factory()->create();
        $yesterday = RawData::factory()->create();
        $this->touchCreatedAt($yesterday, now()->subDay());

        $response = $this->actingAs($user)->get(route('raw-data.index', ['period' => 'today']));

        $ids = $response->viewData('entries')->pluck('id')->all();

        $this->assertContains($today->id, $ids);
        $this->assertNotContains($yesterday->id, $ids);
    }

    public function test_period_week_shows_entries_from_this_week_only(): void
    {
        $user = User::factory()->create();

        $thisWeek = RawData::factory()->create();
        $this->touchCreatedAt($thisWeek, now()->startOfWeek()->addHour());

        $lastWeek = RawData::factory()->create();
        $this->touchCreatedAt($lastWeek, now()->subWeek());

        $response = $this->actingAs($user)->get(route('raw-data.index', ['period' => 'week']));

        $ids = $response->viewData('entries')->pluck('id')->all();

        $this->assertContains($thisWeek->id, $ids);
        $this->assertNotContains($lastWeek->id, $ids);
    }

    public function test_period_month_shows_entries_from_this_month_only(): void
    {
        $user = User::factory()->create();

        $thisMonth = RawData::factory()->create();
        $this->touchCreatedAt($thisMonth, now()->startOfMonth()->addDay());

        $lastMonth = RawData::factory()->create();
        $this->touchCreatedAt($lastMonth, now()->subMonthNoOverflow());

        $response = $this->actingAs($user)->get(route('raw-data.index', ['period' => 'month']));

        $ids = $response->viewData('entries')->pluck('id')->all();

        $this->assertContains($thisMonth->id, $ids);
        $this->assertNotContains($lastMonth->id, $ids);
    }

    public function test_custom_date_range_filters_between_the_given_bounds(): void
    {
        $user = User::factory()->create();

        $inRange = RawData::factory()->create();
        $this->touchCreatedAt($inRange, '2026-06-15 10:00:00');

        $beforeRange = RawData::factory()->create();
        $this->touchCreatedAt($beforeRange, '2026-05-31 23:59:59');

        $afterRange = RawData::factory()->create();
        $this->touchCreatedAt($afterRange, '2026-07-01 00:00:01');

        $response = $this->actingAs($user)->get(route('raw-data.index', [
            'period' => 'custom',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

        $ids = $response->viewData('entries')->pluck('id')->all();

        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($beforeRange->id, $ids);
        $this->assertNotContains($afterRange->id, $ids);
    }

    public function test_custom_date_range_with_only_a_from_bound_is_open_ended(): void
    {
        $user = User::factory()->create();

        $recent = RawData::factory()->create();
        $this->touchCreatedAt($recent, now());

        $old = RawData::factory()->create();
        $this->touchCreatedAt($old, now()->subYear());

        $response = $this->actingAs($user)->get(route('raw-data.index', [
            'period' => 'custom',
            'date_from' => now()->subDay()->toDateString(),
        ]));

        $ids = $response->viewData('entries')->pluck('id')->all();

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }
}
