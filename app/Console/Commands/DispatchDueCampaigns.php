<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class DispatchDueCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-due';

    protected $description = 'Start sending scheduled campaigns whose time has come';

    public function handle(CampaignService $campaigns): int
    {
        $count = $campaigns->dispatchDue();

        if ($count) {
            $this->info("Started {$count} campaign(s).");
        }

        return self::SUCCESS;
    }
}
