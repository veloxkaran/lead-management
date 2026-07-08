<?php

namespace Database\Seeders;

use App\Models\DailySummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DailySummarySeeder extends Seeder
{
    /**
     * Seed daily summaries for the last 5 weekdays for each existing user.
     * Assumes Users already exist in the database.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command?->warn('DailySummarySeeder: no users found, skipping.');

            return;
        }

        $weekdays = [];
        $cursor = Carbon::today();

        while (count($weekdays) < 5) {
            if (! $cursor->isWeekend()) {
                $weekdays[] = $cursor->copy();
            }

            $cursor->subDay();
        }

        $achievements = [
            'Followed up with 5 leads and updated their status.',
            'Closed a deal with a mid-sized client and sent the contract.',
            'Ran product demos for two prospective customers.',
            'Prepared and sent financial proposals to 3 leads.',
            'Handled inbound inquiries and qualified new leads.',
            'Completed onboarding call with a newly converted customer.',
            'Negotiated pricing with a hesitant prospect.',
        ];

        $plans = [
            'Follow up on pending proposals and schedule demo calls.',
            'Reach out to cold leads from last week.',
            'Prepare the contract for the client agreeing to terms.',
            'Continue implementation support for the new customer.',
            'Send the revised financial proposal after feedback.',
            'Set up meetings with two new leads.',
        ];

        $blockers = [
            null,
            null,
            null,
            'Waiting on legal review of the contract terms.',
            'Client has not responded to the last two follow-ups.',
            'Need approval on discount pricing from management.',
        ];

        foreach ($users as $user) {
            foreach ($weekdays as $date) {
                DailySummary::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'summary_date' => $date->format('Y-m-d'),
                    ],
                    [
                        'achieved_today' => $achievements[array_rand($achievements)],
                        'planned_tomorrow' => $plans[array_rand($plans)],
                        'blockers' => $blockers[array_rand($blockers)],
                    ]
                );
            }
        }
    }
}
