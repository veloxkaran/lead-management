<?php

namespace App\Repositories;

use App\Models\ActivityLogEntry;
use App\Models\DealClosure;
use App\Models\LeadStatusHistory;
use App\Models\PolicyDocumentVersion;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Settings\ActivityFeedSettings;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ActivityLogRepository extends BaseRepository
{
    public function __construct(ActivityLogEntry $model, protected ActivityFeedSettings $settings)
    {
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
            ->with([
                'user.assignedDepartment',
                // Eager-loads the right nested relation per subject type in
                // one query per type (not one query per row) — without this,
                // ActivityLinkResolver's per-row `$subject->lead` /
                // `$subject->policyDocument` access is a lazy N+1 on every
                // page, for every poll, from every open dashboard.
                'subject' => fn ($morphTo) => $morphTo->morphWith([
                    LeadStatusHistory::class => ['lead'],
                    DealClosure::class => ['lead'],
                    WhatsappMessage::class => ['lead'],
                    PolicyDocumentVersion::class => ['policyDocument'],
                ]),
            ])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page));
    }
}
