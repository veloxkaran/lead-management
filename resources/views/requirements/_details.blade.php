{{-- Full requirement body, author and attachments — the expanded row under a requirement in a list. --}}
@if ($requirement->title)
    <div class="fw-semibold mb-1">{{ $requirement->title }}</div>
@endif
<div class="requirement-rich-content small">{!! $requirement->requirementHtml() !!}</div>
<div class="d-flex flex-wrap gap-3 mt-2 small text-muted">
    <span><i class="bi bi-person"></i> Created by {{ $requirement->creator?->name ?? 'Unknown' }}</span>
    @if ($requirement->attachments->isNotEmpty())
        <span>
            <i class="bi bi-paperclip"></i>
            @foreach ($requirement->attachments as $attachment)
                <a href="{{ route('requirement-attachments.download', $attachment) }}" class="text-decoration-none me-2" title="Download {{ $attachment->original_name }}">{{ $attachment->original_name }}</a>
            @endforeach
        </span>
    @endif
</div>
