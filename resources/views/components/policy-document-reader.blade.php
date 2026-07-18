@props(['version'])

@php
    $document = $version->policyDocument;
@endphp

<div class="policy-document-reader">
    <div class="policy-document-reader__header">
        <h5 class="fw-semibold mb-1">{{ $document->title }}</h5>
        <div class="small text-muted d-flex flex-wrap gap-3">
            <span><i class="bi bi-diagram-3"></i> {{ $document->user?->name ?? '—' }}</span>
            <span><i class="bi bi-tag"></i> v{{ $version->version }}</span>
            <span><i class="bi bi-calendar-event"></i> Effective {{ $version->effective_date->format('M d, Y') }}</span>
            <span><i class="bi bi-clock-history"></i> Updated {{ $version->published_at->format('M d, Y') }}</span>
            <span><i class="bi bi-book"></i> {{ $version->estimatedReadingMinutes() }} min read</span>
        </div>
    </div>
    <div class="policy-document-reader__body">
        {!! $version->content !!}
    </div>
</div>
