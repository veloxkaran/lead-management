<?php

namespace Tests\Feature;

use App\Enums\RawDataStatus;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\RawData;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWhatsNewTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_view_scopes_every_metric_to_today(): void
    {
        $user = User::factory()->create();
        $status = LeadStatus::factory()->create(['name' => 'New']);

        $convertedLead = Lead::factory()->create(['lead_status_id' => $status->id, 'created_at' => now()]);
        Lead::factory()->create(['lead_status_id' => $status->id, 'created_at' => now()]);
        Lead::factory()->create(['lead_status_id' => $status->id, 'created_at' => now()->subDays(3)]);

        RawData::factory()->create(['created_at' => now()]);
        RawData::factory()->create([
            'created_at' => now(),
            'status' => RawDataStatus::ConvertedToLead->value,
            'converted_lead_id' => $convertedLead->id,
        ]);
        RawData::factory()->create(['created_at' => now()->subDays(3)]);

        SupportTicket::factory()->create(['created_at' => now()]);
        SupportTicket::factory()->create(['created_at' => now()->subDays(3)]);
        SupportTicket::factory()->create(['created_at' => now()->subDays(3), 'resolved_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('newRawDataCount', 2);
        $response->assertViewHas('convertedRawDataCount', 1);
        $response->assertViewHas('ticketsRaisedCount', 1);
        $response->assertViewHas('ticketsSolvedCount', 1);
        $response->assertViewHas('whatsNewFilters', ['period' => 'today', 'date_from' => null, 'date_to' => null]);
        $response->assertViewHas(
            'newLeadsByStatus',
            fn ($statuses) => $statuses->count() === 1 && $statuses->first()->leads_count === 2
        );
    }

    public function test_custom_range_scopes_metrics_to_the_given_bounds(): void
    {
        $user = User::factory()->create();

        SupportTicket::factory()->create(['created_at' => now()->subDays(10)]); // outside range
        SupportTicket::factory()->create(['created_at' => now()->subDays(5)]); // inside range
        SupportTicket::factory()->create(['created_at' => now()]); // outside range (too recent)

        $response = $this->actingAs($user)->get(route('dashboard', [
            'period' => 'custom',
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->subDays(2)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertViewHas('ticketsRaisedCount', 1);
        $response->assertViewHas(
            'whatsNewFilters',
            fn ($filters) => $filters['period'] === 'custom' && $filters['date_from'] === now()->subDays(7)->toDateString()
        );
    }

    public function test_custom_range_with_no_bounds_falls_back_to_today_instead_of_running_unbounded(): void
    {
        $user = User::factory()->create();

        SupportTicket::factory()->create(['created_at' => now()->subDays(10)]);
        SupportTicket::factory()->create(['created_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard', ['period' => 'custom']));

        $response->assertOk();
        $response->assertViewHas('ticketsRaisedCount', 1);
    }
}
