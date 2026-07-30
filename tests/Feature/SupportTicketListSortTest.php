<?php

namespace Tests\Feature;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_sorts_by_status_then_priority_by_default(): void
    {
        $user = User::factory()->create();

        $completedUrgent = SupportTicket::factory()->create([
            'subject' => 'Completed Urgent',
            'status' => RequirementStatus::Completed->value,
            'priority' => RequirementPriority::Urgent->value,
        ]);
        $pendingLow = SupportTicket::factory()->create([
            'subject' => 'Pending Low',
            'status' => RequirementStatus::Pending->value,
            'priority' => RequirementPriority::Low->value,
        ]);
        $pendingHigh = SupportTicket::factory()->create([
            'subject' => 'Pending High',
            'status' => RequirementStatus::Pending->value,
            'priority' => RequirementPriority::High->value,
        ]);
        $onHoldMedium = SupportTicket::factory()->create([
            'subject' => 'On Hold Medium',
            'status' => RequirementStatus::OnHold->value,
            'priority' => RequirementPriority::Medium->value,
        ]);

        $response = $this->actingAs($user)->get(route('support-tickets.index'));

        $response->assertOk();

        $ids = $response->viewData('tickets')->pluck('id')->all();

        $this->assertSame([
            $pendingHigh->id,
            $pendingLow->id,
            $onHoldMedium->id,
            $completedUrgent->id,
        ], $ids);
    }
}
