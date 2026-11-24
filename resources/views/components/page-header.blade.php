@props(['title', 'icon' => null, 'subtitle' => null])

<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
    <div class="d-flex align-items-center gap-3 min-w-0">
        @if ($icon)
            <span class="page-header-icon"><i class="bi {{ $icon }}"></i></span>
        @endif
        <div class="min-w-0">
            <h1 class="h5 fw-semibold mb-0 text-truncate">{{ $title }}</h1>
            @if (isset($meta))
                {{-- Subtitle with extra inline bits after it (e.g. the lead's status). --}}
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if ($subtitle)
                        <p class="text-muted small mb-0">{{ $subtitle }}</p>
                    @endif
                    {{ $meta }}
                </div>
            @elseif ($subtitle)
                <p class="text-muted small mb-0">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if (isset($actions))
        <div class="d-flex flex-wrap align-items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
