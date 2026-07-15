@props(['title', 'icon' => null])

<div {{ $attributes->merge(['class' => 'card border-0 shadow-sm mb-3']) }}>
    <div class="card-header bg-white fw-semibold">
        @if ($icon)
            <i class="bi {{ $icon }} me-1"></i>
        @endif
        {{ $title }}
    </div>
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
