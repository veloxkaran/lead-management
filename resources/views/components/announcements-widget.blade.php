@props(['announcements'])

{{-- Latest announcements on every role's dashboard (data from DashboardController::greeting()). --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-broadcast me-1"></i> Announcements</span>
        <div class="d-flex gap-2">
            @can('create', App\Models\Announcement::class)
                <a href="{{ route('announcements.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> New</a>
            @endcan
            <a href="{{ route('announcements.index') }}" class="btn btn-sm btn-outline-secondary">View all</a>
        </div>
    </div>
    <ul class="list-group list-group-flush">
        @forelse ($announcements as $announcement)
            <li class="list-group-item">
                <a href="{{ route('announcements.show', $announcement) }}" class="d-flex gap-3 text-decoration-none text-body">
                    @if ($image = $announcement->images->first())
                        <img src="{{ $image->url() }}" alt="" class="rounded flex-shrink-0" style="width: 56px; height: 56px; object-fit: cover;">
                    @else
                        <span class="rounded bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px;">
                            <i class="bi bi-megaphone fs-5"></i>
                        </span>
                    @endif
                    <div style="min-width: 0;">
                        <div class="fw-semibold small">
                            {{ $announcement->title }}
                            @if ($announcement->created_at->gt(now()->subDays(3)))
                                <span class="badge bg-primary ms-1">New</span>
                            @endif
                        </div>
                        <div class="small text-muted">{{ \Illuminate\Support\Str::limit($announcement->content, 140) }}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            {{ $announcement->creator?->name ?? 'Team' }} · {{ $announcement->created_at->diffForHumans() }}
                            @if ($announcement->documents_count)
                                · <i class="bi bi-paperclip"></i> {{ $announcement->documents_count }}
                            @endif
                        </div>
                    </div>
                </a>
            </li>
        @empty
            <li class="list-group-item small text-muted text-center py-3">No announcements yet.</li>
        @endforelse
    </ul>
</div>
