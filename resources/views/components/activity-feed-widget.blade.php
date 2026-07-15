@php
    $activityFeedSettings = app(\App\Settings\ActivityFeedSettings::class);
    $activityFeedEnabled = $activityFeedSettings->enabled();
    $refreshSeconds = $activityFeedSettings->refreshSeconds();
@endphp

@if ($activityFeedEnabled)
    <div class="card border-0 shadow-sm h-100 activity-feed-widget" x-data="activityFeed({{ $refreshSeconds }})">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-activity me-1"></i> Activity Feed</span>
            <span class="badge bg-success-subtle text-success-emphasis" x-cloak x-show="!loading">
                <i class="bi bi-broadcast"></i> Live
            </span>
        </div>
        <div class="card-body p-0 activity-feed-list">
            <template x-for="item in items" :key="item.id">
                <div
                    class="activity-feed-item"
                    :class="{ 'activity-feed-item--clickable': item.can_view }"
                    @click="item.can_view && (window.location = item.url)"
                    x-transition:enter="activity-feed-item-enter"
                    x-transition:enter-start="activity-feed-item-enter-start"
                    x-transition:enter-end="activity-feed-item-enter-end"
                >
                    <span class="activity-feed-avatar" x-text="item.user_initial"></span>
                    <div class="activity-feed-body">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold small" x-text="item.user_name"></span>
                            <span class="badge bg-primary-subtle text-primary-emphasis small">
                                <i :class="'bi ' + item.module_icon"></i> <span x-text="item.module_label"></span>
                            </span>
                            <span class="text-muted small" x-show="item.department" x-text="item.department"></span>
                        </div>
                        <div class="small" x-text="item.description"></div>
                        <div class="text-muted" style="font-size: 0.75rem;" x-text="item.time_ago"></div>
                    </div>
                </div>
            </template>
            <div class="text-center text-muted small py-4" x-show="!loading && items.length === 0">
                No recent activity.
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center" x-show="lastPage > 1">
            <button type="button" class="btn btn-sm btn-outline-secondary" @click="goToPage(page - 1)" :disabled="page <= 1">
                <i class="bi bi-chevron-left"></i> Previous
            </button>
            <span class="small text-muted">Page <span x-text="page"></span> of <span x-text="lastPage"></span></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" @click="goToPage(page + 1)" :disabled="page >= lastPage">
                Next <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
@endif
