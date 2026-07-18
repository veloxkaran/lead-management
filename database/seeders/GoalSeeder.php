<?php

namespace Database\Seeders;

use App\Enums\GoalCategory;
use App\Models\Goal;
use App\Models\User;
use App\Services\GoalContributionService;
use Illuminate\Database\Seeder;

class GoalSeeder extends Seeder
{
    /**
     * Seed sample Organization goals. Assumes Users already exist. Achieved
     * amounts are not faked — GoalContributionService resyncs each from
     * real closed deals right after creation, the same way it would in
     * production.
     */
    public function run(GoalContributionService $goalContributions): void
    {
        $creator = User::inRandomOrder()->first();

        if (! $creator) {
            $this->command?->warn('GoalSeeder: no users found, skipping.');

            return;
        }

        $goalsByTitle = [
            'Annual Revenue Target' => GoalCategory::AnnualRevenueTarget,
            'New Customer Acquisition' => GoalCategory::NewClientAcquisition,
            'Client Retention Rate' => GoalCategory::CustomerRetention,
        ];

        foreach ($goalsByTitle as $title => $category) {
            $goal = Goal::factory()->create([
                'title' => $title,
                'category' => $category,
                'target' => fake()->randomFloat(2, 500000, 2000000),
                'achieved' => 0,
                'created_by' => $creator->id,
            ]);

            $goalContributions->resyncGoal($goal);
        }
    }
}
