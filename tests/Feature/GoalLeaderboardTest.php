<?php

namespace Tests\Feature;

use App\Enums\GoalCategory;
use App\Enums\UserRole;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function closeDeal(User $closer, Lead $lead, float $value, ?string $date = null): void
    {
        $this->actingAs($closer)->post(route('leads.close', $lead), [
            'closed_date' => $date ?? now()->toDateString(),
            'deal_value' => $value,
            'closing_comment' => 'Signed.',
        ])->assertRedirect();
    }

    public function test_ranks_contributors_by_total_contribution_across_two_closers(): void
    {
        $topCloser = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $secondCloser = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $leadA = Lead::factory()->create(['assigned_user_id' => $topCloser->id, 'created_by' => $topCloser->id]);
        $leadB = Lead::factory()->create(['assigned_user_id' => $secondCloser->id, 'created_by' => $secondCloser->id]);

        Goal::factory()->create([
            'category' => GoalCategory::MonthlyRevenueTarget,
            'target' => 1000000,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $this->closeDeal($topCloser, $leadA, 700000);
        $this->closeDeal($secondCloser, $leadB, 300000);

        $viewer = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $response = $this->actingAs($viewer)->get(route('goals.leaderboard'));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) use ($topCloser, $secondCloser) {
            $ordered = $rows->pluck('user_id')->values()->all();

            return $ordered === [$topCloser->id, $secondCloser->id];
        });
    }

    public function test_goal_filter_narrows_the_leaderboard(): void
    {
        $closer = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $otherCloser = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $closer->id, 'created_by' => $closer->id]);
        $otherLead = Lead::factory()->create(['assigned_user_id' => $otherCloser->id, 'created_by' => $otherCloser->id]);

        $goal = Goal::factory()->create([
            'category' => GoalCategory::MonthlyRevenueTarget,
            'target' => 1000000,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);
        // A second deal-driven goal with a non-overlapping period — its
        // closer's deal must not bleed into $goal's contributor list, which
        // is what this test is actually isolating.
        Goal::factory()->create([
            'category' => GoalCategory::QuarterlyRevenueTarget,
            'target' => 1000000,
            'achieved' => 0,
            'start_date' => now()->subDays(90),
            'end_date' => now()->subDays(60),
        ]);

        $this->closeDeal($closer, $lead, 500000);
        $this->closeDeal($otherCloser, $otherLead, 400000, now()->subDays(70)->toDateString());

        $response = $this->actingAs($closer)->get(route('goals.leaderboard', ['goal_id' => $goal->id]));

        $response->assertOk();
        $response->assertViewHas('rows', fn ($rows) => $rows->pluck('user_id')->all() === [$closer->id]);
    }

    public function test_date_range_filter_narrows_the_leaderboard(): void
    {
        $closer = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create(['assigned_user_id' => $closer->id, 'created_by' => $closer->id]);

        Goal::factory()->create([
            'category' => GoalCategory::MonthlyRevenueTarget,
            'target' => 1000000,
            'achieved' => 0,
            'start_date' => now()->subDays(40),
            'end_date' => now()->addDays(40),
        ]);

        $this->actingAs($closer)->post(route('leads.close', $lead), [
            'closed_date' => now()->subDays(30)->toDateString(),
            'deal_value' => 500000,
            'closing_comment' => 'Signed.',
        ])->assertRedirect();

        $response = $this->actingAs($closer)->get(route('goals.leaderboard', [
            'date_from' => now()->subDays(5)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertViewHas('rows', fn ($rows) => $rows->isEmpty());
    }

    public function test_every_role_can_view_the_leaderboard(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::BusinessDevelopment, UserRole::CustomerSuccess, UserRole::Finance] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('goals.leaderboard'))->assertOk();
        }
    }
}
