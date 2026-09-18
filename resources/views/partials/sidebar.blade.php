@php
    $user = auth()->user();
@endphp
<aside class="app-sidebar">
    <div class="brand">
        <i class="bi bi-kanban fs-4"></i>
        <span>{{ config('app.name') }}</span>
    </div>
    <nav class="nav flex-column py-2">
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-speedometer2"></i> <span class="nav-label">Dashboard</span>
        </a>
        <a href="{{ route('tasks.index') }}" class="nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}" title="Tasks" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-list-task"></i> <span class="nav-label">Tasks</span>
        </a>

        @if ($user?->isBusinessDevelopment() || $user?->isManager() || $user?->isSuperAdmin())
            <div class="nav-section-title">Business Development</div>
            <a href="{{ route('leads.index') }}" class="nav-link {{ request()->routeIs('leads.*') ? 'active' : '' }}" title="Lead Management" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-diagram-3"></i> <span class="nav-label">Lead Management</span>
            </a>
            <a href="{{ route('raw-data.index') }}" class="nav-link {{ request()->routeIs('raw-data.*') ? 'active' : '' }}" title="Raw Data" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-inbox"></i> <span class="nav-label">Raw Data</span>
            </a>
            <a href="{{ route('requirements.index') }}" class="nav-link {{ request()->routeIs('requirements.*') ? 'active' : '' }}" title="Requirements" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-list-check"></i> <span class="nav-label">Requirements</span>
            </a>
            <a href="{{ route('follow-ups.index') }}" class="nav-link {{ request()->routeIs('follow-ups.*') ? 'active' : '' }}" title="Follow Ups" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-bell"></i> <span class="nav-label">Follow Ups</span>
            </a>
        @endif

        <div class="nav-section-title">Support</div>
        <a href="{{ route('support-tickets.index') }}" class="nav-link {{ request()->routeIs('support-tickets.*') ? 'active' : '' }}" title="Support Tickets" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-life-preserver"></i> <span class="nav-label">Support Tickets</span>
        </a>

        <div class="nav-section-title">Knowledge</div>
        <a href="{{ route('knowledge-base.index') }}" class="nav-link {{ request()->routeIs('knowledge-base.*') ? 'active' : '' }}" title="Knowledge Base" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-journal-richtext"></i> <span class="nav-label">Knowledge Base</span>
        </a>
        <a href="{{ route('release-notes.index') }}" class="nav-link {{ request()->routeIs('release-notes.*') ? 'active' : '' }}" title="Release Notes" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-megaphone"></i> <span class="nav-label">Release Notes</span>
        </a>

        <div class="nav-section-title">Reporting</div>
        <a href="{{ route('daily-summaries.index') }}" class="nav-link {{ request()->routeIs('daily-summaries.*') ? 'active' : '' }}" title="Daily Summary" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-journal-text"></i> <span class="nav-label">Daily Summary</span>
        </a>
        <a href="{{ route('team.index') }}" class="nav-link {{ request()->routeIs('team.index') ? 'active' : '' }}" title="My Team" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-people-fill"></i> <span class="nav-label">My Team</span>
        </a>
        <a href="{{ route('team.activities') }}" class="nav-link {{ request()->routeIs('team.activities') ? 'active' : '' }}" title="Team Activities" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-clock-history"></i> <span class="nav-label">Team Activities</span>
        </a>
        @can('viewOrgTree')
            <a href="{{ route('org-tree.index') }}" class="nav-link {{ request()->routeIs('org-tree.*') ? 'active' : '' }}" title="Organization Tree" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-diagram-3"></i> <span class="nav-label">Organization Tree</span>
            </a>
        @endcan
        @if ($user?->isSuperAdmin())
            <div class="nav-section-title">Administration</div>
            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" title="Users" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-people"></i> <span class="nav-label">Users</span>
            </a>
            <a href="{{ route('lead-statuses.index') }}" class="nav-link {{ request()->routeIs('lead-statuses.*') ? 'active' : '' }}" title="Lead Statuses" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-signpost-split"></i> <span class="nav-label">Lead Statuses</span>
            </a>
            <a href="{{ route('settings.edit') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" title="Settings" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-gear"></i> <span class="nav-label">Settings</span>
            </a>
        @endif

        <div class="nav-section-title">Account</div>
        <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" title="Profile" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-person-circle"></i> <span class="nav-label">Profile</span>
        </a>
        <a href="{{ route('email-accounts.index') }}" class="nav-link {{ request()->routeIs('email-accounts.*') ? 'active' : '' }}" title="Email Accounts" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-envelope-at"></i> <span class="nav-label">Email Accounts</span>
        </a>
        <a href="{{ route('password.edit') }}" class="nav-link {{ request()->routeIs('password.edit') ? 'active' : '' }}" title="Change Password" data-bs-toggle="tooltip" data-bs-placement="right">
            <i class="bi bi-key"></i> <span class="nav-label">Change Password</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start" title="Logout" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-box-arrow-right"></i> <span class="nav-label">Logout</span>
            </button>
        </form>
    </nav>
</aside>
