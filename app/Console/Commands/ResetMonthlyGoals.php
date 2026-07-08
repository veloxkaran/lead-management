<?php

namespace App\Console\Commands;

use App\Models\Goal;
use App\Services\GoalAchievementService;
use App\Support\NepaliCalendar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetMonthlyGoals extends Command
{
    protected $signature = 'goals:reset-monthly';

    protected $description = 'Reset recurring goals whose period has elapsed and roll them into the current Nepali (BS) month';

    public function handle(GoalAchievementService $goalAchievements): int
    {
        $today = now()->startOfDay();
        $currentBs = NepaliCalendar::today();

        $expiredGoals = Goal::whereDate('end_date', '<', $today)->get();

        if ($expiredGoals->isEmpty()) {
            $this->info('No goals due for reset.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($expiredGoals, $currentBs, $today, $goalAchievements) {
            foreach ($expiredGoals as $goal) {
                $periodLength = $goal->start_date->diffInDays($goal->end_date) + 1;

                $goal->update([
                    'achieved' => 0,
                    'start_date' => $today,
                    'end_date' => (clone $today)->addDays(max($periodLength - 1, 27)),
                    'bs_year' => $currentBs['year'],
                    'bs_month' => $currentBs['month'],
                ]);

                $goalAchievements->recalculate($goal->refresh());
            }
        });

        $this->info("Reset {$expiredGoals->count()} goal(s) for the new Nepali month: ".NepaliCalendar::label($currentBs));

        return self::SUCCESS;
    }
}
