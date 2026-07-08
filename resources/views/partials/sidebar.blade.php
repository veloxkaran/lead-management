@php
    $user = auth()->user();
@endphp
<aside class="app-sidebar">
    <div class="brand">
        <i class="bi bi-kanban fs-4"></i>
        <span>{{ config('app.name') }}</span>
    </div>
    <nav class="nav flex-column py-2">
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section-title">Business Development</div>
        <a href="{{ route('leads.index') }}" class="nav-link {{ request()->routeIs('leads.*') ? 'active' : '' }}">
            <i class="bi bi-diagram-3"></i> Lead Management
        </a>
        <a href="{{ route('activities.index') }}" class="nav-link {{ request()->routeIs('activities.*') ? 'active' : '' }}">
            <i class="bi bi-clock-history"></i> Activities
        </a>
        <a href="{{ route('requirements.index') }}" class="nav-link {{ request()->routeIs('requirements.*') ? 'active' : '' }}">
            <i class="bi bi-list-check"></i> Requirements
        </a>
        <a href="{{ route('follow-ups.index') }}" class="nav-link {{ request()->routeIs('follow-ups.*') ? 'active' : '' }}">
            <i class="bi bi-bell"></i> Follow Ups
        </a>
        <a href="{{ route('goals.index') }}" class="nav-link {{ request()->routeIs('goals.*') ? 'active' : '' }}">
            <i class="bi bi-bullseye"></i> Goals
        </a>
        <a href="{{ route('meetings.index') }}" class="nav-link {{ request()->routeIs('meetings.*') ? 'active' : '' }}">
            <i class="bi bi-camera-video"></i> Google Meet
        </a>

        <div class="nav-section-title">Knowledge</div>
        <a href="{{ route('knowledge-base.index') }}" class="nav-link {{ request()->routeIs('knowledge-base.*') ? 'active' : '' }}">
            <i class="bi bi-journal-richtext"></i> Knowledge Base
        </a>
        <a href="{{ route('release-notes.index') }}" class="nav-link {{ request()->routeIs('release-notes.*') ? 'active' : '' }}">
            <i class="bi bi-megaphone"></i> Release Notes
        </a>

        <div class="nav-section-title">Reporting</div>
        <a href="{{ route('daily-summaries.index') }}" class="nav-link {{ request()->routeIs('daily-summaries.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i> Daily Summary
        </a>
        @if ($user?->isSuperAdmin())
            <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>
        @else
            <a href="{{ route('common-reports.personal-achievement') }}" class="nav-link {{ request()->routeIs('common-reports.*') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>
        @endif

        @if ($user?->isSuperAdmin())
            <div class="nav-section-title">Administration</div>
            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i> Users
            </a>
            <a href="{{ route('lead-statuses.index') }}" class="nav-link {{ request()->routeIs('lead-statuses.*') ? 'active' : '' }}">
                <i class="bi bi-signpost-split"></i> Lead Statuses
            </a>
            <a href="{{ route('settings.edit') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="bi bi-gear"></i> Settings
            </a>
        @endif

        <div class="nav-section-title">Account</div>
        <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <i class="bi bi-person-circle"></i> Profile
        </a>
        <a href="{{ route('password.edit') }}" class="nav-link {{ request()->routeIs('password.edit') ? 'active' : '' }}">
            <i class="bi bi-key"></i> Change Password
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </form>
    </nav>
</aside>
