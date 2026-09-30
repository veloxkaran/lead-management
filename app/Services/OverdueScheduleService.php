<?php

namespace App\Services;

use App\Enums\PermissionModule;
use App\Enums\RawDataStatus;
use App\Enums\RequirementStatus;
use App\Enums\TaskStatus;
use App\Models\FollowUp;
use App\Models\RawData;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;

/**
 * Drives the blinking "overdue" flag on sidebar menu items: how many of the
 * member's own scheduled items are past due, per component. "Own" means
 * what they're on the hook for — assigned to them (or, for follow-ups, the
 * lead's follow-up they scheduled or own) — not everything they can see, so
 * an overseer's menu doesn't blink for the whole team's backlog. Each rule
 * mirrors the component's existing overdue definition (FollowUp::due(),
 * Task/Requirement::isOverdue(), the Raw Data assignment countdown).
 */
class OverdueScheduleService
{
    /**
     * @return array<string, int> PermissionModule value => overdue count,
     *                            only for components the member can view
     *                            and that have something overdue.
     */
    public function countsFor(User $user): array
    {
        $counters = [
            PermissionModule::FollowUps->value => fn () => $this->followUps($user),
            PermissionModule::Tasks->value => fn () => $this->tasks($user),
            PermissionModule::Requirements->value => fn () => $this->requirements($user),
            PermissionModule::RawData->value => fn () => $this->rawData($user),
        ];

        $counts = [];

        foreach ($counters as $module => $count) {
            if ($user->hasPermission($module) && ($total = $count()) > 0) {
                $counts[$module] = $total;
            }
        }

        return $counts;
    }

    private function followUps(User $user): int
    {
        return FollowUp::due()
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('lead', fn ($lead) => $lead->where('assigned_user_id', $user->id));
            })
            ->count();
    }

    private function tasks(User $user): int
    {
        return Task::where('assigned_to', $user->id)
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today())
            ->count();
    }

    private function requirements(User $user): int
    {
        return Requirement::where('assigned_to', $user->id)
            ->where('status', '!=', RequirementStatus::Completed->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today())
            ->count();
    }

    private function rawData(User $user): int
    {
        return RawData::where('assigned_to', $user->id)
            ->whereNotIn('status', [RawDataStatus::NotValid->value, RawDataStatus::ConvertedToLead->value])
            ->where('assigned_at', '<=', now()->subHours(RawData::ASSIGNMENT_RESPONSE_HOURS))
            ->count();
    }
}
