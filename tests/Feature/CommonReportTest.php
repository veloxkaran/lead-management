<?php

namespace Tests\Feature;

use App\Enums\GoalCategory;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommonReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_goal_vs_achievement_lists_every_goal(): void
    {
        $user = User::factory()->create();
        Goal::factory()->create();
        Goal::factory()->create();

        $response = $this->actingAs($user)->get(route('common-reports.goal-vs-achievement'));

        $response->assertOk();
        $response->assertViewHas('goals', fn ($goals) => $goals->count() === 2);
    }

    public function test_my_contributions_lists_only_the_viewers_own_contributions(): void
    {
        $closer = User::factory()->create();
        $otherCloser = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $closer->id, 'created_by' => $closer->id]);
        $otherLead = Lead::factory()->create(['assigned_user_id' => $otherCloser->id, 'created_by' => $otherCloser->id]);

        Goal::factory()->create([
            'category' => GoalCategory::MonthlyRevenueTarget,
            'target' => 1000000,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $this->actingAs($closer)->post(route('leads.close', $lead), [
            'closed_date' => now()->toDateString(),
            'deal_value' => 500000,
            'closing_comment' => 'Signed.',
        ])->assertRedirect();

        $this->actingAs($otherCloser)->post(route('leads.close', $otherLead), [
            'closed_date' => now()->toDateString(),
            'deal_value' => 300000,
            'closing_comment' => 'Signed.',
        ])->assertRedirect();

        $response = $this->actingAs($closer)->get(route('common-reports.my-contributions'));

        $response->assertOk();
        $response->assertViewHas('contributions', fn ($contributions) => $contributions->total() === 1);
        $response->assertViewHas('total', 500000.0);
    }
}
