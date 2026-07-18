<?php

namespace App\Console\Commands;

use App\Models\Goal;
use App\Models\Lead;
use App\Support\Currency;
use App\Support\SlackNotifier;
use Illuminate\Console\Command;

class SendDailySlackSummary extends Command
{
    protected $signature = 'slack:daily-summary';

    protected $description = 'Post the daily target-vs-achievement summary and monthly company status list to Slack';

    public function handle(SlackNotifier $slack): int
    {
        $lines = ['*'.config('app.name').' — Daily Summary ('.now()->format('M d, Y').')*'];

        $orgGoals = Goal::all();

        if ($orgGoals->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Target vs Achievement:*';

            foreach ($orgGoals as $goal) {
                $lines[] = "• {$goal->title}: ".Currency::format($goal->achieved).' / '.Currency::format($goal->target)." ({$goal->achievementPercentage()}%)";
            }
        }

        $companies = Lead::where('created_at', '>=', now()->startOfMonth())
            ->with('status')
            ->get()
            ->groupBy(fn (Lead $lead) => $lead->status?->name ?? 'Unassigned');

        if ($companies->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Monthly Company Status List:*';

            foreach ($companies as $statusName => $leads) {
                $lines[] = "• {$statusName}: ".$leads->pluck('company_name')->implode(', ');
            }
        }

        $sent = $slack->send(implode("\n", $lines));

        $this->info($sent ? 'Daily Slack summary sent.' : 'Slack webhook not configured; skipped.');

        return self::SUCCESS;
    }
}
