<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history"></i> Assignment History</div>
    <div class="card-body">
        @forelse ($rawData->assignmentLogs as $log)
            <div class="border-bottom pb-2 mb-2 small d-flex align-items-center gap-2">
                <x-status-badge :status="$log->action" />
                <span><strong>{{ $log->user?->name ?? 'Unknown' }}</strong></span>
                <span class="text-muted">by {{ $log->performedBy?->name ?? 'Unknown' }} on {{ $log->created_at->format('M d, Y g:i A') }}</span>
            </div>
        @empty
            <p class="text-muted small mb-0">No assignment history yet.</p>
        @endforelse
    </div>
</div>
