<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lead.achieved_at still flips on an is_achievement-flagged status
 * transition — it feeds ReportService's "Achieved Cost" export column and
 * the lead show page's Achieved badge. It no longer feeds Goal achievement
 * (see GoalContributionTest for that, now driven by DealClosure instead).
 */
class LeadAchievementFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_a_lead_into_an_achievement_status_sets_achieved_at(): void
    {
        $user = User::factory()->create();
        $regularStatus = LeadStatus::factory()->create(['is_achievement' => false]);
        $achievementStatus = LeadStatus::factory()->create(['is_achievement' => true]);

        $lead = Lead::factory()->create([
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'lead_status_id' => $regularStatus->id,
            'achieved_cost' => 5000,
        ]);

        $this->actingAs($user)->post(route('leads.status.update', $lead), [
            'lead_status_id' => $achievementStatus->id,
        ])->assertRedirect();

        $this->assertNotNull($lead->fresh()->achieved_at);
    }

    public function test_moving_a_lead_out_of_an_achievement_status_clears_achieved_at(): void
    {
        $user = User::factory()->create();
        $achievementStatus = LeadStatus::factory()->create(['is_achievement' => true]);
        $regularStatus = LeadStatus::factory()->create(['is_achievement' => false]);

        $lead = Lead::factory()->create([
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'lead_status_id' => $achievementStatus->id,
            'achieved_cost' => 3000,
            'achieved_at' => now(),
        ]);

        $this->actingAs($user)->post(route('leads.status.update', $lead), [
            'lead_status_id' => $regularStatus->id,
        ])->assertRedirect();

        $this->assertNull($lead->fresh()->achieved_at);
    }
}
