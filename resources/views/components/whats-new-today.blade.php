@props(['filters', 'newLeadsByStatus', 'newRawDataCount', 'convertedRawDataCount', 'ticketsRaisedCount', 'ticketsSolvedCount', 'newRequirementsCount'])

{{-- "What's New Today?" — rendered identically on every role dashboard
     (see resources/views/dashboard/*.blade.php). Defaults to today; the
     period/date_from/date_to filter re-submits the whole dashboard GET
     request (App\Http\Controllers\DashboardController::whatsNewToday()),
     same period vocabulary as the Raw Data list filter. --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-stars me-1"></i> What's New Today?</span>
        <form method="GET" action="{{ route('dashboard') }}" class="row g-2 align-items-end" x-data="{ period: '{{ $filters['period'] ?? 'today' }}' }">
            <div class="col-auto">
                <select name="period" class="form-select form-select-sm" x-model="period">
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div class="col-auto" x-show="period === 'custom'" x-cloak>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto" x-show="period === 'custom'" x-cloak>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small text-muted mb-2">New Leads by Status</div>
                @forelse ($newLeadsByStatus as $status)
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $status->name }}</span>
                        <span class="fw-semibold">{{ $status->leads_count }}</span>
                    </div>
                @empty
                    <div class="small text-muted fst-italic">No new leads</div>
                @endforelse
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small text-muted mb-2">Raw Data Added</div>
                <div class="fs-4 fw-semibold">{{ $newRawDataCount }}</div>
                <div class="small text-muted">{{ $convertedRawDataCount }} converted to lead</div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small text-muted mb-2">Tickets Raised</div>
                <div class="fs-4 fw-semibold">{{ $ticketsRaisedCount }}</div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small text-muted mb-2">Tickets Solved</div>
                <div class="fs-4 fw-semibold">{{ $ticketsSolvedCount }}</div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="small text-muted mb-2">Requirements Added</div>
                <div class="fs-4 fw-semibold">{{ $newRequirementsCount }}</div>
            </div>
        </div>
    </div>
</div>
