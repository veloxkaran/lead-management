<?php

namespace App\Events;

use App\Models\Requirement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequirementSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(public Requirement $requirement, public bool $wasCreated)
    {
    }
}
