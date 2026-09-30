@props(['status'])

@php
    $class = method_exists($status, 'badgeClass') ? $status->badgeClass() : 'bg-secondary';
    $label = method_exists($status, 'label') ? $status->label() : (string) $status;

    // Enums declare a solid colour (e.g. "bg-success", "bg-info text-dark");
    // render it as a soft tinted pill so lists stay calm and scannable.
    $tone = preg_match('/\bbg-(primary|secondary|success|danger|warning|info|light|dark)\b/', $class, $m) ? $m[1] : 'secondary';
    $softClass = "bg-{$tone}-subtle text-{$tone}-emphasis border border-{$tone}-subtle";
@endphp

<span {{ $attributes->merge(['class' => "badge $softClass"]) }}>{{ $label }}</span>
