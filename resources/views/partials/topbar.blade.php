@php
    $user = auth()->user();
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
@endphp
<header class="app-topbar">
    <div class="d-flex align-items-center gap-2">
        <button id="sidebarToggle" class="btn btn-sm btn-light d-lg-none" type="button" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>
        <button id="sidebarCollapseToggle" class="btn btn-sm btn-light d-none d-lg-inline-flex" type="button" title="Toggle sidebar" aria-label="Toggle sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
        <h2 class="h6 mb-0 text-muted fw-normal">@yield('title')</h2>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-light topbar-search" data-bs-toggle="modal" data-bs-target="#commandPalette" aria-label="Quick search (Ctrl+K)" title="Quick search (Ctrl+K)">
            <i class="bi bi-search"></i>
            <span class="topbar-search-label d-none d-md-inline">Search or jump to…</span>
            <kbd class="topbar-search-kbd d-none d-md-inline" data-shortcut-label>Ctrl K</kbd>
        </button>
        @php
            $currentTheme = $user?->uiTheme() ?? App\Enums\UiTheme::DEFAULT;
        @endphp
        <div class="dropdown">
            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-label="Theme" title="Theme">
                <i class="bi bi-palette"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
                <h6 class="dropdown-header">Theme</h6>
                @foreach (App\Enums\UiTheme::cases() as $theme)
                    <form method="POST" action="{{ route('preferences.theme.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="theme" value="{{ $theme->value }}">
                        <button type="submit" @class(['dropdown-item d-flex align-items-center gap-2', 'active' => $currentTheme === $theme]) @if ($currentTheme === $theme) aria-current="true" @endif>
                            <span class="theme-swatch" style="background: {{ $theme->swatch() }};"></span>
                            <span class="flex-grow-1">{{ $theme->label() }}</span>
                            @if ($currentTheme === $theme)
                                <i class="bi bi-check2"></i>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-light position-relative" type="button" data-bs-toggle="dropdown" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                @if ($unreadCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        {{ $unreadCount }}
                    </span>
                @endif
            </button>
            @php
                $unreadNotifications = $user?->unreadNotifications()->take(8)->get() ?? collect();
                $mentionCount = $unreadNotifications->where('data.type', 'mention')->count();
            @endphp
            <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 320px; max-height: 380px; overflow-y: auto;" x-data="{ tab: 'all' }">
                <div class="d-flex align-items-center justify-content-between px-2 pb-2">
                    <h6 class="dropdown-header p-0 m-0">Notifications</h6>
                    {{-- "Mentions" = someone @mentioned you in a comment. --}}
                    <div class="btn-group btn-group-sm notification-tabs" role="group" aria-label="Filter notifications">
                        <button type="button" class="btn" :class="tab === 'all' ? 'btn-primary' : 'btn-outline-secondary'" @click.stop="tab = 'all'">All</button>
                        <button type="button" class="btn" :class="tab === 'mentions' ? 'btn-primary' : 'btn-outline-secondary'" @click.stop="tab = 'mentions'">
                            Mentions @if ($mentionCount)<span class="badge rounded-pill bg-danger ms-1">{{ $mentionCount }}</span>@endif
                        </button>
                    </div>
                </div>
                @forelse ($unreadNotifications as $notification)
                    @php $isMention = ($notification->data['type'] ?? null) === 'mention'; @endphp
                    {{-- No d-flex on the <a> itself: its display:flex !important would beat x-show's display:none. --}}
                    <a href="{{ route('notifications.read', $notification->id) }}" class="dropdown-item small text-wrap" x-show="tab === 'all' || {{ $isMention ? 'true' : 'false' }}">
                        <span class="d-flex gap-2">
                            <i class="bi {{ $isMention ? 'bi-at text-primary' : 'bi-dot' }} flex-shrink-0"></i>
                            <span>
                                {{ $notification->data['message'] ?? 'New notification' }}
                                <span class="d-block text-muted" style="font-size: 0.72rem;">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                        </span>
                    </a>
                @empty
                    <div class="dropdown-item small text-muted">No new notifications</div>
                @endforelse
                @if ($unreadNotifications->isNotEmpty() && ! $mentionCount)
                    <div class="dropdown-item small text-muted" x-show="tab === 'mentions'" x-cloak>No unread mentions</div>
                @endif
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
