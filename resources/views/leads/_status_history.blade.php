<div class="timeline">
    @forelse ($lead->statusHistories as $history)
        <div class="timeline-item">
            <div class="d-flex justify-content-between">
                <span class="small">
                    @if ($history->fromStatus)
                        <span class="badge bg-light text-dark border">{{ $history->fromStatus->name }}</span> &rarr;
                    @endif
                    <span class="badge" style="background-color: {{ $history->toStatus->color }}">{{ $history->toStatus->name }}</span>
                </span>
                <span class="text-muted small">{{ $history->changed_at->format('M d, Y g:i A') }}</span>
            </div>
            <span class="text-muted" style="font-size: 0.72rem;">
                by {{ $history->changedBy?->name }}
                @if ($history->seconds_in_previous_status)
                    · spent {{ \Illuminate\Support\Carbon::now()->subSeconds($history->seconds_in_previous_status)->diffForHumans(null, true) }} in previous status
                @endif
            </span>
        </div>
    @empty
        <x-empty-state icon="bi-signpost-split" title="No status changes recorded" />
    @endforelse
</div>
