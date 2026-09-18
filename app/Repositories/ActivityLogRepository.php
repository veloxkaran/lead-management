<?php

namespace App\Repositories;

use App\Models\ActivityLogEntry;
use App\Models\DealClosure;
use App\Models\LeadNote;
use App\Models\LeadStatusHistory;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\OrganizationHierarchyService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;

class ActivityLogRepository extends BaseRepository
{
    public function __construct(
        ActivityLogEntry $model,
        protected OrganizationHierarchyService $hierarchy,
    ) {
        parent::__construct($model);
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
