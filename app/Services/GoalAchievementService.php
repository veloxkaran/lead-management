<?php

namespace App\Services;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\LeadStatus;

/**
 * Keeps Goal::$achieved in sync with the sum of achieved_cost across leads
 * flagged as "achievement" (via LeadStatus::$is_achievement) whose
 * achieved_at falls inside the goal's period. Goals are fully recomputed
 * (not incremented/decremented) so a lead's achieved_cost, assigned user,
 * or achievement status can change freely without any drift risk.
 */
class GoalAchievementService
{
    /**
     * Sets or clears a lead's achieved_at when it crosses in/out of an
     * achievement-flagged status, then resyncs any goals it could affect.
     * Only flips achieved_at on transition — moving between two
     * achievement-flagged statuses leaves the original achieved_at (and
     * thus which goal period it counts toward) intact.
     */
    public function applyStatusToLead(Lead $lead, ?LeadStatus $status): void
    {
        $isAchievement = $status?->is_achievement ?? false;

        if ($isAchievement && ! $lead->achieved_at) {
            $lead->update(['achieved_at' => now()]);
        } elseif (! $isAchievement && $lead->achieved_at) {
            $lead->update(['achieved_at' => null]);
        }

        $this->syncForLead($lead);
    }

    public function syncForLead(Lead $lead, ?int $previousAssignedUserId = null, ?int $previousTeamId = null): void
    {
        $lead->loadMissing('assignedUser');

        $goals = Goal::query()
            ->where(function ($query) use ($lead, $previousAssignedUserId, $previousTeamId) {
                $query->where('goal_type', GoalType::Organization);

                $userIds = array_filter(array_unique([$lead->assigned_user_id, $previousAssignedUserId]));
                if (! empty($userIds)) {
                    $query->orWhere(fn ($q) => $q->where('goal_type', GoalType::Individual)->whereIn('user_id', $userIds));
                }

                $teamIds = array_filter(array_unique([$lead->assignedUser?->team_id, $previousTeamId]));
                if (! empty($teamIds)) {
                    $query->orWhere(fn ($q) => $q->where('goal_type', GoalType::Team)->whereIn('team_id', $teamIds));
                }
            })
            ->get();

        $goals->each($this->recalculate(...));
    }

    public function recalculate(Goal $goal): void
    {
        $query = Lead::query()->achieved()
            ->whereBetween('achieved_at', [$goal->start_date->startOfDay(), $goal->end_date->endOfDay()]);

        match ($goal->goal_type) {
            GoalType::Individual => $query->where('assigned_user_id', $goal->user_id),
            GoalType::Team => $query->whereHas('assignedUser', fn ($q) => $q->where('team_id', $goal->team_id)),
            GoalType::Organization => null,
        };

        $achieved = (float) $query->sum('achieved_cost');

        if ((float) $goal->achieved !== $achieved) {
            $goal->update(['achieved' => $achieved]);
        }
    }
}
