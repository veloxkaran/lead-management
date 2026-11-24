{{-- The related lead's pipeline status as a quiet pill: a dot in the status's
     own colour plus its name. Secondary information next to a company name,
     so it stays softer than the record's own status/priority badges. --}}
@props(['lead'])
@if ($lead?->status)
    @php
        $color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $lead->status->color) ? $lead->status->color : '#64748b';
    @endphp
    <span {{ $attributes->merge(['class' => 'lead-status-pill']) }} style="--lead-status-color: {{ $color }};" title="Lead status: {{ $lead->status->name }}">
        <span class="lead-status-dot" aria-hidden="true"></span>{{ $lead->status->name }}
    </span>
@endif
