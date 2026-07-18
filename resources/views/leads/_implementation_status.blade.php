@php $impl = $lead->latestImplementationRequest; @endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-box-arrow-in-up-right"></i> Implementation Status</h6>
        @if ($impl)
            <div class="d-flex justify-content-between align-items-center mb-2">
                <x-status-badge :status="$impl->status" />
                <span class="small text-muted">Updated {{ $impl->updated_at->diffForHumans() }}</span>
            </div>
            <div class="progress mb-2" style="height: 6px;">
                <div class="progress-bar" role="progressbar" style="width: {{ $impl->completion_percentage }}%" aria-valuenow="{{ $impl->completion_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <dl class="row small mb-0">
                <dt class="col-6 text-muted">Engineer</dt><dd class="col-6">{{ $impl->assignee?->name ?? '—' }}</dd>
                <dt class="col-6 text-muted">Planned Date</dt><dd class="col-6">{{ $impl->planned_date?->format('M d, Y') ?? '—' }}</dd>
                <dt class="col-6 text-muted">Completed On</dt><dd class="col-6">{{ $impl->completed_at?->format('M d, Y') ?? '—' }}</dd>
                <dt class="col-6 text-muted">Phase</dt><dd class="col-6">{{ $impl->phase ?? '—' }}</dd>
                <dt class="col-6 text-muted">Completion</dt><dd class="col-6">{{ $impl->completion_percentage }}%</dd>
            </dl>
            @if ($impl->notes)
                <hr>
                <p class="small text-muted mb-0">{{ $impl->notes }}</p>
            @endif
        @else
            <x-empty-state icon="bi-box-arrow-in-up-right" title="No implementation started yet" />
        @endif
    </div>
    <div class="card-footer bg-white">
        <a href="{{ route('implementation-requests.index', ['lead_id' => $lead->id]) }}" class="btn btn-sm btn-outline-primary w-100">Open Implementation Details</a>
    </div>
</div>
