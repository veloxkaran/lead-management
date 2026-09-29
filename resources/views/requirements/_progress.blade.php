{{-- Completed-out-of-total bar for one RequirementSummary, with an overdue chip when any are late. --}}
<div class="d-flex align-items-center gap-2" style="min-width: 180px;">
    <div class="progress flex-grow-1" style="height: 6px;" role="progressbar" aria-label="Requirements completed" aria-valuenow="{{ $summary->completionPercent() }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar bg-success" style="width: {{ $summary->completionPercent() }}%"></div>
    </div>
    <span class="small text-muted text-nowrap">{{ $summary->completed }}/{{ $summary->total }} done</span>
</div>
@if ($summary->overdue)
    <span class="badge bg-danger-subtle text-danger-emphasis mt-1"><i class="bi bi-exclamation-triangle"></i> {{ $summary->overdue }} overdue</span>
@endif
