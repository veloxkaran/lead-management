<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadStatus;

/**
 * Keeps Lead::$achieved_at in sync with whether the lead's current status is
 * flagged as an achievement (LeadStatus::$is_achievement) — this still feeds
 * ReportService's "Achieved Cost" export column and the lead show page's
 * Achieved badge. It no longer feeds Goal achievement: Organization goals
 * are now driven by DealClosure via GoalContributionService, since that's
 * the event with real per-person attribution ("who closed the deal"),
 * which a lead's achieved_cost never had.
 */
class LeadAchievementService
{
    /**
     * Sets or clears a lead's achieved_at when it crosses in/out of an
     * achievement-flagged status. Only flips achieved_at on transition —
     * moving between two achievement-flagged statuses leaves the original
     * achieved_at intact.
     */
    public function applyStatusToLead(Lead $lead, ?LeadStatus $status): void
    {
        $isAchievement = $status?->is_achievement ?? false;

        if ($isAchievement && ! $lead->achieved_at) {
            $lead->update(['achieved_at' => now()]);
        } elseif (! $isAchievement && $lead->achieved_at) {
            $lead->update(['achieved_at' => null]);
        }
    }
}
