@props(['title', 'icon' => null, 'subtitle' => null])

<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h1 class="h4 fw-semibold mb-0">
            @if ($icon)
                <i class="bi {{ $icon }} text-primary me-1"></i>
            @endif
            {{ $title }}
        </h1>
        @if ($subtitle)
            <p class="text-muted small mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="d-flex gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
