@props(['record', 'noun'])

{{-- Why a Cancelled requirement/support ticket was cancelled, by whom and when (see Cancellable). --}}
@if ($record->isCancelled())
    <div class="alert alert-danger d-flex gap-2 py-2 small" role="status">
        <i class="bi bi-x-octagon-fill mt-1"></i>
        <div>
            <div class="fw-semibold">
                This {{ $noun }} was cancelled
                @if ($record->canceller) by {{ $record->canceller->name }}@endif
                @if ($record->cancelled_at) on {{ $record->cancelled_at->format('M d, Y g:i A') }}@endif.
            </div>
            <div class="mt-1" style="white-space: pre-line;">{{ $record->cancellation_reason ?: 'No reason recorded.' }}</div>
        </div>
    </div>
@endif
