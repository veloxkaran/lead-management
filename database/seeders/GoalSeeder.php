<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\User;
use App\Services\GoalAchievementService;
use Illuminate\Database\Seeder;

class GoalSeeder extends Seeder
{
    /**
     * Seed sample goals. Assumes Users already exist in the database.
     * Achieved amounts are not faked — GoalAchievementService recalculates
     * them from real achievement-flagged leads right after each is created,
     * the same way it would in production.
     */
    public function run(GoalAchievementService $goalAchievements): void
    {
        $creator = User::inRandomOrder()->first();

        if (! $creator) {
            $this->command?->warn('GoalSeeder: no users found, skipping.');

            return;
        }

        // 2-3 Organization goals.
        $orgTitles = ['Annual Revenue Target', 'New Customer Acquisition', 'Client Retention Rate'];
        foreach ($orgTitles as $title) {
            $goal = Goal::factory()->organization()->create([
                'title' => $title,
                'target' => fake()->randomFloat(2, 500000, 2000000),
                'created_by' => $creator->id,
            ]);

            $goalAchievements->recalculate($goal);
        }

        // One Individual goal per existing user.
        User::all()->each(function (User $user) use ($creator, $goalAchievements) {
            $goal = Goal::factory()->individual()->create([
                'title' => 'Personal Sales Target',
                'target' => fake()->randomFloat(2, 20000, 150000),
                'user_id' => $user->id,
                'created_by' => $creator->id,
            ]);

            $goalAchievements->recalculate($goal);
        });
    }
}
