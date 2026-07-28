<?php

namespace App\Repositories;

use App\Models\ActivityLogEntry;
use App\Models\DealClosure;
use App\Models\LeadNote;
use App\Models\LeadStatusHistory;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\OrganizationHierarchyService;
use App\Settings\ActivityFeedSettings;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ActivityLogRepository extends BaseRepository
{
    public function __construct(
        ActivityLogEntry $model,
        protected ActivityFeedSettings $settings,
        protected OrganizationHierarchyService $hierarchy,
    ) {
        parent::__construct($model);
    }

    /**
     * Cached for a few seconds — short enough to stay well under the
     * minimum configurable refresh interval (5s, enforced in
     * ActivityFeedSettingsController's validation), so a poll never waits
     * more than one cache window behind real time, but long enough to
     * absorb the fact that every open dashboard polls this independently.
     *
     * The cache key encodes the viewer's *effective* scope, not just their
     * company_id: BelongsToCompany bypasses filtering entirely for Super
     * Admins, so a Super Admin's "every company" result must never be
     * cached under the same key as a specific company's scoped result (or
     * vice versa) — that would leak one tenant's activity into another's
     * feed the next time the same key is read.
     */
    public function feedForViewer(User $viewer, int $page): LengthAwarePaginator
    {
        $perPage = $this->settings->perPage();
        $enabledModules = $this->settings->enabledModules();
        $scopeKey = $viewer->isSuperAdmin() ? 'all' : 'company:'.($viewer->company_id ?? 'none');
        $cacheKey = sprintf(
            'activity_feed:%s:modules:%s:page:%d:per:%d',
            $scopeKey,
            md5(implode(',', $enabledModules)),
            $page,
            $perPage,
        );

        return Cache::remember($cacheKey, now()->addSeconds(5), fn () => $this->query()
            ->whereIn('module', $enabledModules)
            ->with(['user', 'subject' => $this->morphSubjectRelations()])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page));
    }

    /**
     * Hierarchy-scoped, filterable feed backing the Team Activities page —
     * overseers (Manager/Super Admin) get the same unrestricted company-wide
     * access they already have everywhere else; everyone else is restricted
     * to their own (in)direct reports, matching
     * OrganizationHierarchyService::getTeamStatistics()'s existing
     * subordinate-id scoping (deliberately excludes the viewer themselves,
     * same as the Team page's member list).
     *
     * @param  array{user_id?: int, module?: string, company_id?: int, date_from?: string, date_to?: string, search?: string}  $filters
     */
    public function feedForTeam(User $viewer, array $filters, int $page, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['user', 'subject' => $this->morphSubjectRelations()]);

        if (! $viewer->isOverseer()) {
            $subordinateIds = $this->hierarchy->getAllSubordinateIds($viewer)->all();

            if (empty($subordinateIds)) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('user_id', $subordinateIds);
            }
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (! empty($filters['company_id']) && $viewer->isSuperAdmin()) {
            $query->where('company_id', $filters['company_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('description', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")));
        }

        return $query->latest()->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    /**
     * Eager-loads the right nested relation per subject type in one query
     * per type (not one query per row) — without this, per-row
     * `$subject->lead` access (used by both ActivityLinkResolver and the
     * Team Activities view) is a lazy N+1 on every page.
     */
    private function morphSubjectRelations(): callable
    {
        return fn (MorphTo $morphTo) => $morphTo->morphWith([
            LeadStatusHistory::class => ['lead'],
            DealClosure::class => ['lead'],
            WhatsappMessage::class => ['lead'],
            LeadNote::class => ['lead'],
        ]);
    }
}
