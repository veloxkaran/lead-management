@props(['icon' => 'bi-inbox', 'title' => 'Nothing here yet', 'description' => null])

<div class="text-center text-muted py-5">
    <span class="empty-state-icon mb-3"><i class="bi {{ $icon }}"></i></span>
    <p class="mb-1 fw-semibold text-body">{{ $title }}</p>
    @if ($description)
        <p class="small mb-0">{{ $description }}</p>
    @endif
</div>
