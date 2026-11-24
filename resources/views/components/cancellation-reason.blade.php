@props(['record', 'limit' => 80])

{{-- One-line cancellation reason under a status badge in lists; full text on hover. --}}
@if ($record->isCancelled() && filled($record->cancellation_reason))
    <div class="small text-muted text-break mt-1" style="max-width: 18rem;" title="{{ $record->cancellation_reason }}">
        <i class="bi bi-chat-left-quote"></i> {{ Str::limit($record->cancellation_reason, $limit) }}
    </div>
@endif
