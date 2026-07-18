<?php

namespace App\Services;

use App\Models\ActivityLogEntry;
use App\Models\DailySummary;
use App\Models\DealClosure;
use App\Models\User;
use App\Repositories\UserHierarchyRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for "who can see whose data" derived purely from
 * User::reporting_manager_id — no role is hardcoded as a manager here
 * (except the existing isOverseer() company-wide bypass, reused rather than
 * replaced). Reusable across any module that needs "my team's data": wired
 * into the Team page, Org Tree page, and team statistics (DailySummary,
 * Goals, Activity Feed) today; any future module can call visibleUserIds()
 * or canView() the same way.
 */
class OrganizationHierarchyService
{
    public function __construct(protected UserHierarchyRepository $hierarchy) {}

    /**
     * @return Collection<int, User>
     */
    public function getDirectReports(User $manager): Collection
    {
        return $this->hierarchy->getDirectReports($manager);
    }

    /**
     * Every (in)direct subordinate id of $manager, recursively resolved in
     * one query. Cached for 10 minutes — reporting-line changes are rare
     * (an admin action), unlike the Activity Feed's 5s poll — and
     * invalidated explicitly by User::booted() when reporting_manager_id
     * changes anywhere in the chain, so staleness is bounded by that event,
     * not by the TTL, in the common case.
     *
     * Returns via ->values(): the array cache driver (used in tests) hands
     * back the exact same Collection object on every read, not a copy —
     * without this, a caller doing ->push($viewer->id) on the result (a
     * common idiom for "subordinates plus self") would mutate the cached
     * collection in place, permanently corrupting it for every subsequent
     * read of that key. ->values() returns a fresh Collection instance
     * wrapping a fresh array, so callers can safely mutate what they get
     * back. Harmless in production (the database cache driver
     * serializes/deserializes on every read, so this was never reachable
     * there) but a real bug under the array driver.
     *
     * @return Collection<int, int>
     */
    public function getAllSubordinateIds(User $manager): Collection
    {
        return Cache::remember(
            "org_hierarchy:subordinate_ids:{$manager->id}",
            now()->addMinutes(10),
            fn () => $this->hierarchy->getAllSubordinateIds($manager->id)
        )->values();
    }

    /**
     * Purely factual — does $user have any direct or indirect report? No
     * SuperAdmin special-casing here (that's isOverseer()'s job).
     */
    public function hasSubordinates(User $user): bool
    {
        return $this->getAllSubordinateIds($user)->isNotEmpty();
    }

    /**
     * @return Collection<int, User>
     */
    public function getAllSubordinates(User $manager): Collection
    {
        $ids = $this->getAllSubordinateIds($manager);

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Walks reporting_manager_id upward from $user to the root, nearest
     * manager first. Not cached: bounded by org depth (capped at 50, same
     * as the recursive CTE), and cheap relative to the subordinate-id
     * lookup this codebase actually calls on every request.
     *
     * @return Collection<int, User>
     */
    public function getManagerChain(User $user): Collection
    {
        $chain = collect();
        $current = $user->reportingManager;
        $seen = [];

        while ($current && ! in_array($current->id, $seen, true)) {
            $chain->push($current);
            $seen[] = $current->id;
            $current = $current->reportingManager;
        }

        return $chain;
    }

    /**
     * One getAllRowsForTree() query, reshaped into a nested array in two
     * linear passes (index by id, then attach each node to its parent's
     * children bucket) — no per-node query, and no recursion in the build
     * itself (only rendering the result is recursive, via
     * <x-org-tree-node>).
     *
     * @return array<int, array{user: object, children: array}>
     */
    public function getOrganizationTree(?int $companyId = null, ?int $rootUserId = null): array
    {
        $rows = $this->hierarchy->getAllRowsForTree($companyId);

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row->id] = ['user' => $row, 'children' => []];
        }

        $roots = [];
        foreach ($byId as $id => &$node) {
            $parentId = $node['user']->reporting_manager_id;

            if ($parentId && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$node;
            } elseif (! $rootUserId) {
                $roots[] = &$node;
            }
        }
        unset($node);

        if ($rootUserId) {
            return isset($byId[$rootUserId]) ? [$byId[$rootUserId]] : [];
        }

        return $roots;
    }

    /**
     * Bulk-composed from DailySummary/ActivityLogEntry/DealClosure, all
     * keyed by the single subordinate-id list above — a fixed number of
     * queries regardless of team size, matching the keyed-collection idiom
     * already used by ActivityLogRepository::feedForViewer().
     *
     * "Attendance Today" has no real data source in this app (no Attendance
     * module exists) — proxied as "submitted a DailySummary today" and
     * exposed as `reported_today`, deliberately not labeled "attendance" in
     * the data layer to avoid overstating what it means.
     *
     * @return array{members: Collection<int, User>, reported_today: Collection<int, int>, latest_summary: Collection<int, DailySummary>, latest_activity: Collection<int, ActivityLogEntry>, weekly_performance: Collection<int, object>, monthly_performance: Collection<int, object>}
     */
    public function getTeamStatistics(User $manager): array
    {
        $subordinateIds = $this->getAllSubordinateIds($manager)->all();

        if (empty($subordinateIds)) {
            return [
                'members' => collect(),
                'reported_today' => collect(),
                'latest_summary' => collect(),
                'latest_activity' => collect(),
                'weekly_performance' => collect(),
                'monthly_performance' => collect(),
            ];
        }

        $today = now()->toDateString();

        $reportedToday = DailySummary::whereIn('user_id', $subordinateIds)
            ->whereDate('summary_date', $today)
            ->pluck('user_id')->flip();

        $latestSummary = DailySummary::whereIn('user_id', $subordinateIds)
            ->whereIn('id', function ($query) use ($subordinateIds) {
                $query->selectRaw('MAX(id)')->from('daily_summaries')
                    ->whereIn('user_id', $subordinateIds)->groupBy('user_id');
            })->get()->keyBy('user_id');

        $latestActivity = ActivityLogEntry::whereIn('user_id', $subordinateIds)
            ->whereIn('id', function ($query) use ($subordinateIds) {
                $query->selectRaw('MAX(id)')->from('activity_log_entries')
                    ->whereIn('user_id', $subordinateIds)->groupBy('user_id');
            })->get()->keyBy('user_id');

        // Bounded date-range queries rather than strftime week-bucketing:
        // SQLite's strftime('%W', ...) numbers weeks Monday-first in a way
        // that doesn't reliably line up with PHP's now()->startOfWeek(), so
        // comparing "this week" via a formatted string key is fragile.
        // Explicit date ranges avoid that ambiguity entirely.
        $weeklyPerformance = DealClosure::whereIn('closed_by', $subordinateIds)
            ->whereBetween('closed_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->selectRaw('closed_by, count(*) as deals, sum(deal_value) as value')
            ->groupBy('closed_by')->get()->keyBy('closed_by');

        $monthlyPerformance = DealClosure::whereIn('closed_by', $subordinateIds)
            ->whereBetween('closed_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw('closed_by, count(*) as deals, sum(deal_value) as value')
            ->groupBy('closed_by')->get()->keyBy('closed_by');

        return [
            'members' => $this->getAllSubordinates($manager),
            'reported_today' => $reportedToday,
            'latest_summary' => $latestSummary,
            'latest_activity' => $latestActivity,
            'weekly_performance' => $weeklyPerformance,
            'monthly_performance' => $monthlyPerformance,
        ];
    }

    /**
     * Overseers (SuperAdmin/Manager) keep today's existing flat, unchanged
     * company-wide access — this is additive, not a replacement. Everyone
     * else gets themselves plus their recursive subordinate ids.
     *
     * @return Collection<int, int>
     */
    public function visibleUserIds(User $viewer): Collection
    {
        if ($viewer->isOverseer()) {
            return User::where('company_id', $viewer->company_id)->pluck('id');
        }

        return $this->getAllSubordinateIds($viewer)->push($viewer->id);
    }

    public function canView(User $viewer, User $target): bool
    {
        if ($viewer->id === $target->id || $viewer->isOverseer()) {
            return true;
        }

        return $this->getAllSubordinateIds($viewer)->contains($target->id);
    }
}
