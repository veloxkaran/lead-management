@props([
    'snapshotPeriod',
    'linkFilters' => [],
    'ticketsCreated',
    'ticketsSolved',
    'ticketsAvgSolvingTime',
    'requirementsCreated',
    'requirementsClosed',
    'requirementsAvgClosingTime',
    'leadsGenerated',
    'leadsConverted',
    'leadsConversionRatio',
])

{{-- "Performance Snapshot" — rendered identically on every role dashboard
     (see resources/views/dashboard/*.blade.php). Independent of the
     "What's New Today?" widget's period filter: this one is a simple
     Daily/This Month switch (App\Http\Controllers\DashboardController::
     performanceSnapshot()) driving the three throughput-and-average-time
     groups the dashboard needs front and center. --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-graph-up-arrow me-1"></i> Performance Snapshot</span>
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('dashboard', array_merge($linkFilters, ['snapshot_period' => 'daily'])) }}"
               class="btn {{ $snapshotPeriod === 'daily' ? 'btn-primary' : 'btn-outline-primary' }}">Daily</a>
            <a href="{{ route('dashboard', array_merge($linkFilters, ['snapshot_period' => 'monthly'])) }}"
               class="btn {{ $snapshotPeriod === 'monthly' ? 'btn-primary' : 'btn-outline-primary' }}">Monthly</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-2"><i class="bi bi-life-preserver me-1"></i> Support Tickets</div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Created</span><span class="fw-semibold">{{ $ticketsCreated }}</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Solved</span><span class="fw-semibold">{{ $ticketsSolved }}</span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Avg. Solving Time</span><span>{{ $ticketsAvgSolvingTime }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-2"><i class="bi bi-clipboard-check me-1"></i> Requirements</div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Created</span><span class="fw-semibold">{{ $requirementsCreated }}</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Closed</span><span class="fw-semibold">{{ $requirementsClosed }}</span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Avg. Closing Time</span><span>{{ $requirementsAvgClosingTime }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-2"><i class="bi bi-diagram-3 me-1"></i> Leads</div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Generated</span><span class="fw-semibold">{{ $leadsGenerated }}</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Converted</span><span class="fw-semibold">{{ $leadsConverted }}</span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Conversion Ratio</span><span>{{ $leadsConversionRatio !== null ? $leadsConversionRatio.'%' : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
