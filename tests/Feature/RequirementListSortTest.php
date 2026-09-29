<?php

namespace Tests\Feature;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_sorts_like_support_tickets_status_then_priority_then_newest(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        $make = function (RequirementStatus $status, RequirementPriority $priority, int $daysAgo) use ($lead) {
            $requirement = Requirement::factory()->create(['lead_id' => $lead->id, 'status' => $status, 'priority' => $priority]);
            $requirement->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

            return $requirement->id;
        };

        $completedUrgent = $make(RequirementStatus::Completed, RequirementPriority::Urgent, 1);
        $inProgressUrgent = $make(RequirementStatus::InProgress, RequirementPriority::Urgent, 1);
        $pendingLow = $make(RequirementStatus::Pending, RequirementPriority::Low, 1);
        $pendingUrgentOld = $make(RequirementStatus::Pending, RequirementPriority::Urgent, 5);
        $pendingUrgentNew = $make(RequirementStatus::Pending, RequirementPriority::Urgent, 2);
        $onHoldHigh = $make(RequirementStatus::OnHold, RequirementPriority::High, 1);

        $expected = [$pendingUrgentNew, $pendingUrgentOld, $pendingLow, $inProgressUrgent, $onHoldHigh, $completedUrgent];

        $listIds = $this->actingAs($user)->get(route('requirements.index'))->viewData('requirements')->pluck('id')->all();
        $companyIds = $this->actingAs($user)->get(route('requirements.company', $lead))->viewData('requirements')->pluck('id')->all();

        $this->assertSame($expected, $listIds);
        $this->assertSame($expected, $companyIds);
    }
}
