{{-- "Performance Snapshot" — rendered identically on every role dashboard
     (see resources/views/dashboard/*.blade.php). Entirely self-contained:
     fetches its own data from GET /dashboard/performance-snapshot on mount
     and on every Daily/Monthly/Lifetime switch (resources/js/performance-
     snapshot.js), so switching periods never triggers a page navigation —
     it can't jump the user's scroll position because nothing ever reloads. --}}
<div class="card border-0 shadow-sm mb-3 performance-snapshot" id="performance-snapshot" x-data="performanceSnapshot()">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <span class="fw-semibold"><i class="bi bi-graph-up-arrow me-1"></i> Performance Snapshot</span>
            <div class="small text-muted" x-cloak x-show="!loading" x-text="data.dateLabel"></div>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Snapshot period">
            <button type="button" class="btn" :class="period === 'daily' ? 'btn-primary' : 'btn-outline-primary'" @click="switchPeriod('daily')">Daily</button>
            <button type="button" class="btn" :class="period === 'monthly' ? 'btn-primary' : 'btn-outline-primary'" @click="switchPeriod('monthly')">Monthly</button>
            <button type="button" class="btn" :class="period === 'lifetime' ? 'btn-primary' : 'btn-outline-primary'" @click="switchPeriod('lifetime')">Lifetime</button>
        </div>
    </div>
    <div class="card-body position-relative">
        <div class="row g-3" style="transition: opacity .15s ease;" :style="loading && 'opacity: .45'">
            <div class="col-lg-4">
                <div class="performance-snapshot-group border-start border-4 border-primary ps-3 h-100">
                    <div class="small text-uppercase text-muted fw-semibold mb-2"><i class="bi bi-life-preserver me-1"></i> Support Tickets</div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <span class="text-muted small">Created</span>
                        <span class="fs-4 fw-semibold" x-text="data.tickets ? data.tickets.created : 0"></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <span class="text-muted small">Solved</span>
                        <span class="fs-4 fw-semibold" x-text="data.tickets ? data.tickets.solved : 0"></span>
                    </div>
                    {{-- Formatted inline (not in performance-snapshot.js) so no asset rebuild is needed. --}}
                    <div class="small text-muted">Solving Ratio — <span class="fw-semibold text-body" x-text="data.tickets && data.tickets.ratio != null ? data.tickets.ratio + '%' : '—'"></span></div>
                    <div class="small text-muted">Avg. Solving Time — <span class="fw-semibold text-body" x-text="data.tickets ? data.tickets.avgTime : '—'"></span></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="performance-snapshot-group border-start border-4 border-warning ps-3 h-100">
                    <div class="small text-uppercase text-muted fw-semibold mb-2"><i class="bi bi-clipboard-check me-1"></i> Requirements</div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <span class="text-muted small">Created</span>
                        <span class="fs-4 fw-semibold" x-text="data.requirements ? data.requirements.created : 0"></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <span class="text-muted small">Closed</span>
                        <span class="fs-4 fw-semibold" x-text="data.requirements ? data.requirements.closed : 0"></span>
                    </div>
                    <div class="small text-muted">Solving Ratio — <span class="fw-semibold text-body" x-text="data.requirements && data.requirements.ratio != null ? data.requirements.ratio + '%' : '—'"></span></div>
                    <div class="small text-muted">Avg. Closing Time — <span class="fw-semibold text-body" x-text="data.requirements ? data.requirements.avgTime : '—'"></span></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="performance-snapshot-group border-start border-4 border-success ps-3 h-100">
                    <div class="small text-uppercase text-muted fw-semibold mb-2"><i class="bi bi-diagram-3 me-1"></i> Leads</div>
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <span class="text-muted small">Generated</span>
                        <span class="fs-4 fw-semibold" x-text="data.leads ? data.leads.generated : 0"></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <span class="text-muted small">Converted</span>
                        <span class="fs-4 fw-semibold" x-text="data.leads ? data.leads.converted : 0"></span>
                    </div>
                    <div class="small text-muted">Conversion Ratio — <span class="fw-semibold text-body" x-text="ratioLabel()"></span></div>
                </div>
            </div>
        </div>
        <div class="position-absolute top-50 start-50 translate-middle" x-show="loading" x-cloak>
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        </div>
    </div>
</div>
