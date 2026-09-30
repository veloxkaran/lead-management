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
 * Drives the blinking "overdue" flag on sidebar menu items: how many items
 * in each component are past due across the whole company (BelongsToCompany
 * scopes every query) — not just the viewer's own, so the whole team sees
 * the menu blink until someone deals with it. Each rule mirrors the
 * component's existing overdue definition (FollowUp::due(),
 * Task/Requirement::isOverdue(), the Raw Data assignment countdown). The
 * viewer only matters for permissions: no flag on a component they can't
 * view.
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
            PermissionModule::FollowUps->value => fn () => FollowUp::due()->count(),
            PermissionModule::Tasks->value => fn () => $this->tasks(),
            PermissionModule::Requirements->value => fn () => $this->requirements(),
            PermissionModule::RawData->value => fn () => $this->rawData(),
        ];

        $counts = [];

        foreach ($counters as $module => $count) {
            if ($user->hasPermission($module) && ($total = $count()) > 0) {
                $counts[$module] = $total;
            }
        }

        return $counts;
    }

    private function tasks(): int
    {
        return Task::whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today())
            ->count();
    }

    private function requirements(): int
    {
        return Requirement::where('status', '!=', RequirementStatus::Completed->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', today())
            ->count();
    }

    private function rawData(): int
    {
        return RawData::whereNotNull('assigned_to')
            ->whereNotIn('status', [RawDataStatus::NotValid->value, RawDataStatus::ConvertedToLead->value])
            ->where('assigned_at', '<=', now()->subHours(RawData::ASSIGNMENT_RESPONSE_HOURS))
            ->count();
    }
}
