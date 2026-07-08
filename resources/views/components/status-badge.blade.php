@props(['status'])

@php
    $class = method_exists($status, 'badgeClass') ? $status->badgeClass() : 'bg-secondary';
    $label = method_exists($status, 'label') ? $status->label() : (string) $status;
@endphp

<span {{ $attributes->merge(['class' => "badge $class"]) }}>{{ $label }}</span>
