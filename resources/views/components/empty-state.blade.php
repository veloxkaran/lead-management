@props(['icon' => 'bi-inbox', 'title' => 'Nothing here yet', 'description' => null])

<div class="text-center text-muted py-5">
    <i class="bi {{ $icon }} display-6 d-block mb-2 opacity-50"></i>
    <p class="mb-0 fw-semibold">{{ $title }}</p>
    @if ($description)
        <p class="small mb-0">{{ $description }}</p>
    @endif
</div>
