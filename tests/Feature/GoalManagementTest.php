<?php

namespace Tests\Feature;

use App\Enums\GoalCategory;
use App\Enums\GoalStatus;
use App\Enums\UserRole;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_create_a_goal(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->get(route('goals.create'))->assertForbidden();
    }

    public function test_super_admin_can_create_an_organization_goal_with_category_and_description(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('goals.store'), [
            'title' => 'Q3 Revenue Target',
            'description' => 'Push hard in Q3.',
            'category' => GoalCategory::QuarterlyRevenueTarget->value,
            'target' => 1000000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
        ]);

        $response->assertRedirect(route('goals.index'));
        $this->assertDatabaseHas('goals', [
            'title' => 'Q3 Revenue Target',
            'description' => 'Push hard in Q3.',
            'category' => GoalCategory::QuarterlyRevenueTarget->value,
        ]);
    }

    public function test_every_role_can_view_the_goals_index_and_a_goals_show_page(): void
    {
        $goal = Goal::factory()->create();

        foreach ([UserRole::SuperAdmin, UserRole::Manager, UserRole::BusinessDevelopment, UserRole::CustomerSuccess, UserRole::Finance] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('goals.index'))->assertOk();
            $this->actingAs($user)->get(route('goals.show', $goal))->assertOk();
        }
    }

    public function test_category_filter_narrows_the_index(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Goal::factory()->create(['category' => GoalCategory::TrainingCompletion]);
        Goal::factory()->create(['category' => GoalCategory::CollectionsTarget]);

        $response = $this->actingAs($admin)->get(route('goals.index', ['category' => GoalCategory::TrainingCompletion->value]));

        $response->assertOk();
        $response->assertViewHas('goals', fn ($goals) => $goals->total() === 1);
    }

    public function test_status_filter_narrows_the_index_to_completed_goals(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Goal::factory()->create(['target' => 1000, 'achieved' => 1000]);
        Goal::factory()->create(['target' => 1000, 'achieved' => 200]);

        $response = $this->actingAs($admin)->get(route('goals.index', ['status' => GoalStatus::Completed->value]));

        $response->assertOk();
        $response->assertViewHas('goals', fn ($goals) => $goals->total() === 1);
    }

    public function test_only_super_admin_can_delete_a_goal(): void
    {
        $goal = Goal::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->delete(route('goals.destroy', $goal))->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->delete(route('goals.destroy', $goal))->assertRedirect(route('goals.index'));
        $this->assertDatabaseMissing('goals', ['id' => $goal->id]);
    }
}
