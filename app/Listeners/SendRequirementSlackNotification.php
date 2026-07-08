<?php

namespace App\Listeners;

use App\Events\RequirementSaved;
use App\Support\SlackNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRequirementSlackNotification implements ShouldQueue
{
    public function __construct(protected SlackNotifier $slack)
    {
    }

    public function handle(RequirementSaved $event): void
    {
        $requirement = $event->requirement->loadMissing('lead');

        $this->slack->send("{$requirement->lead->company_name} :: {$requirement->requirement}");
    }
}
