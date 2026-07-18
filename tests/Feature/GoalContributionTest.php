<?php

namespace Tests\Feature;

use App\Enums\GoalCategory;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalContributionTest extends TestCase
{
    use RefreshDatabase;

    private function closeDeal(User $closer, Lead $lead, float $value, ?string $date = null): void
    {
        $this->actingAs($closer)->post(route('leads.close', $lead), [
            'closed_date' => $date ?? now()->toDateString(),
            'deal_value' => $value,
            'closing_comment' => 'Signed and done.',
        ])->assertRedirect();
    }

    public function test_closing_a_deal_credits_a_matching_deal_driven_goal_to_the_closer(): void
    {
        $closer = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $closer->id, 'created_by' => $closer->id]);

        $goal = Goal::factory()->create([
            'category' => GoalCategory::MonthlyRevenueTarget,
            'target' => 1000000,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $this->closeDeal($closer, $lead, 500000);

        $this->assertEquals(500000, (float) $goal->fresh()->achieved);

        $this->assertDatabaseHas('goal_contributions', [
            'goal_id' => $goal->id,
            'user_id' => $closer->id,
            'contribution_type' => 'deal_closed',
            'amount' => '500000.00',
        ]);
    }

    public function test_a_goal_in_a_non_deal_driven_category_is_unaffected(): void
    {
        $closer = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $closer->id, 'created_by' => $closer->id]);

        $goal = Goal::factory()->create([
            'category' => GoalCategory::TrainingCompletion,
            'target' => 100,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $this->closeDeal($closer, $lead, 500000);

        $this->assertEquals(0, (float) $goal->fresh()->achieved);
        $this->assertDatabaseMissing('goal_contributions', ['goal_id' => $goal->id]);
    }

    public function test_new_client_acquisition_goal_counts_deals_not_their_value(): void
    {
        $closerA = User::factory()->create();
        $closerB = User::factory()->create();
        $leadA = Lead::factory()->create(['assigned_user_id' => $closerA->id, 'created_by' => $closerA->id]);
        $leadB = Lead::factory()->create(['assigned_user_id' => $closerB->id, 'created_by' => $closerB->id]);

        $goal = Goal::factory()->create([
            'category' => GoalCategory::NewClientAcquisition,
            'target' => 10,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $this->closeDeal($closerA, $leadA, 500000);
        $this->closeDeal($closerB, $leadB, 300000);

        $this->assertEquals(2, (float) $goal->fresh()->achieved);
    }

    public function test_narrowing_a_goals_date_range_drops_out_of_range_contributions(): void
    {
        $closer = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $closer->id, 'created_by' => $closer->id]);
        $admin = User::factory()->superAdmin()->create();

        $goal = Goal::factory()->create([
            'category' => GoalCategory::MonthlyRevenueTarget,
            'target' => 1000000,
            'achieved' => 0,
            'start_date' => now()->subDays(20),
            'end_date' => now()->addDays(20),
        ]);

        $this->closeDeal($closer, $lead, 500000, now()->subDays(10)->toDateString());
        $this->assertEquals(500000, (float) $goal->fresh()->achieved);

        $this->actingAs($admin)->put(route('goals.update', $goal), [
            'title' => $goal->title,
            'category' => $goal->category->value,
            'target' => $goal->target,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
        ])->assertRedirect();

        $this->assertEquals(0, (float) $goal->fresh()->achieved);
        $this->assertDatabaseMissing('goal_contributions', ['goal_id' => $goal->id]);
    }

    public function test_reclosing_the_same_lead_updates_its_existing_contribution_rather_than_duplicating(): void
    {
        $closer = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $closer->id, 'created_by' => $closer->id]);

        $goal = Goal::factory()->create([
            'category' => GoalCategory::MonthlyRevenueTarget,
            'target' => 1000000,
            'achieved' => 0,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);

        $this->closeDeal($closer, $lead, 500000);
        $this->closeDeal($closer, $lead, 600000);

        $this->assertEquals(1, GoalContribution::where('goal_id', $goal->id)->count());
        $this->assertEquals(600000, (float) $goal->fresh()->achieved);
    }
}
