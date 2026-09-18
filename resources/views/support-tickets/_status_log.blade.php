<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history"></i> Status History</div>
    <div class="card-body">
        @forelse ($supportTicket->statusLogs as $log)
            <div class="border-bottom pb-2 mb-2 small d-flex align-items-center gap-2">
                <x-status-badge :status="$log->from_status" />
                <i class="bi bi-arrow-right text-muted"></i>
                <x-status-badge :status="$log->to_status" />
                <span class="text-muted">by {{ $log->changedBy?->name ?? 'Unknown' }} on {{ $log->created_at->format('M d, Y g:i A') }}</span>
            </div>
        @empty
            <p class="text-muted small mb-0">No status changes yet.</p>
        @endforelse
    </div>
</div>
