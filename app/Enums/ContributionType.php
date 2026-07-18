<?php

namespace App\Enums;

/**
 * The kind of business event a GoalContribution was recorded for —
 * distinct from the goal's own category (one event type can feed several
 * different goal categories). Only DealClosed is wired today; extend here
 * when a deferred source (subscription renewal, training/implementation
 * completion, collections) gets its own automatic tracking.
 */
enum ContributionType: string
{
    case DealClosed = 'deal_closed';

    public function label(): string
    {
        return match ($this) {
            self::DealClosed => 'Deal Closed',
        };
    }
}
