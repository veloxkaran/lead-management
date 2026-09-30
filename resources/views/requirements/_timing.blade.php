{{-- Generated + solved time for one requirement, on the company page. --}}
<div class="small text-nowrap">
    <div><span class="text-muted">Generated:</span> {{ $requirement->created_at->format('M d, Y g:i A') }}</div>
    @if ($requirement->isCompleted())
        <div><span class="text-muted">Solved:</span> {{ $requirement->completed_at?->format('M d, Y g:i A') ?? '—' }}</div>
        <div class="text-success fw-semibold">Solved in {{ $requirement->solvedInFormatted() ?? '—' }}</div>
    @else
        <div class="text-warning-emphasis">Open for <span x-data="ticketElapsed('{{ $requirement->created_at->toIso8601String() }}')" x-text="text">{{ $requirement->elapsedFormatted() }}</span></div>
    @endif
</div>
