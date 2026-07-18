<?php

namespace Tests\Feature;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\GoalAchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalAchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_reaching_an_achievement_status_credits_matching_goals(): void
    {
        $user = User::factory()->create();
        $regularStatus = LeadStatus::factory()->create(['is_achievement' => false]);
        $achievementStatus = LeadStatus::factory()->create(['is_achievement' => true]);

        $lead = Lead::factory()->create([
            'assigned_user_id' => $user->id,
            'lead_status_id' => $regularStatus->id,
            'achieved_cost' => 5000,
        ]);

        $individualGoal = Goal::factory()->create([
            'goal_type' => GoalType::Individual,
            'user_id' => $user->id,
            'target' => 10000,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $organizationGoal = Goal::factory()->create([
            'goal_type' => GoalType::Organization,
            'user_id' => null,
            'target' => 20000,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $this->actingAs($user)->post(route('leads.status.update', $lead), [
            'lead_status_id' => $achievementStatus->id,
        ])->assertRedirect();

        $this->assertNotNull($lead->fresh()->achieved_at);
        $this->assertEquals(5000, (float) $individualGoal->fresh()->achieved);
        $this->assertEquals(5000, (float) $organizationGoal->fresh()->achieved);
    }

    public function test_moving_a_lead_out_of_an_achievement_status_removes_its_credit(): void
    {
        $user = User::factory()->create();
        $achievementStatus = LeadStatus::factory()->create(['is_achievement' => true]);
        $regularStatus = LeadStatus::factory()->create(['is_achievement' => false]);

        $lead = Lead::factory()->create([
            'assigned_user_id' => $user->id,
            'lead_status_id' => $achievementStatus->id,
            'achieved_cost' => 3000,
            'achieved_at' => now(),
        ]);

        $goal = Goal::factory()->create([
            'goal_type' => GoalType::Individual,
            'user_id' => $user->id,
            'target' => 10000,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        app(GoalAchievementService::class)->recalculate($goal);
        $this->assertEquals(3000, (float) $goal->fresh()->achieved);

        $this->actingAs($user)->post(route('leads.status.update', $lead), [
            'lead_status_id' => $regularStatus->id,
        ])->assertRedirect();

        $this->assertNull($lead->fresh()->achieved_at);
        $this->assertEquals(0, (float) $goal->fresh()->achieved);
    }

    public function test_flipping_a_status_achievement_flag_retroactively_resyncs_existing_leads(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $status = LeadStatus::factory()->create(['is_achievement' => false]);

        $lead = Lead::factory()->create([
            'assigned_user_id' => $user->id,
            'lead_status_id' => $status->id,
            'achieved_cost' => 7000,
        ]);

        $goal = Goal::factory()->create([
            'goal_type' => GoalType::Individual,
            'user_id' => $user->id,
            'target' => 10000,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $this->actingAs($admin)->put(route('lead-statuses.update', $status), [
            'name' => $status->name,
            'color' => $status->color,
            'is_achievement' => '1',
        ])->assertRedirect();

        $this->assertNotNull($lead->fresh()->achieved_at);
        $this->assertEquals(7000, (float) $goal->fresh()->achieved);
    }
}
