@php
    $user = auth()->user();
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
@endphp
<header class="app-topbar">
    <div class="d-flex align-items-center gap-2">
        <button id="sidebarToggle" class="btn btn-sm btn-outline-secondary d-lg-none" type="button">
            <i class="bi bi-list"></i>
        </button>
        <button id="sidebarCollapseToggle" class="btn btn-sm btn-outline-secondary d-none d-lg-inline-flex" type="button" title="Toggle sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
        <h2 class="h6 mb-0 text-muted">@yield('title')</h2>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-sm btn-light position-relative" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-bell"></i>
                @if ($unreadCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        {{ $unreadCount }}
                    </span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 320px; max-height: 380px; overflow-y: auto;">
                <h6 class="dropdown-header">Notifications</h6>
                @forelse ($user?->unreadNotifications()->take(8)->get() ?? [] as $notification)
                    <a href="{{ route('notifications.read', $notification->id) }}" class="dropdown-item small text-wrap">
                        {{ $notification->data['message'] ?? 'New notification' }}
                        <div class="text-muted" style="font-size: 0.72rem;">{{ $notification->created_at->diffForHumans() }}</div>
                    </a>
                @empty
                    <div class="dropdown-item small text-muted">No new notifications</div>
                @endforelse
            </div>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                <x-user-avatar :user="$user" :size="28" />
                <span class="small fw-semibold d-none d-sm-inline">{{ $user->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text small text-muted">{{ $user->role?->label() }}</span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="{{ route('password.edit') }}"><i class="bi bi-key me-2"></i>Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
