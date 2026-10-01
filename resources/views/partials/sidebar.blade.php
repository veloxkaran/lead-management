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
        @permitted('tasks')
            <a href="{{ route('tasks.index') }}" class="nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }} {{ isset($overdue['tasks']) ? 'nav-overdue' : '' }}" title="Tasks{{ isset($overdue['tasks']) ? ' · '.$overdue['tasks'].' overdue' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-list-task"></i> <span class="nav-label">Tasks</span>
                @isset($overdue['tasks'])<span class="nav-overdue-flag">overdue</span>@endisset
            </a>
        @endpermitted

        @php
            $bdModules = ['leads', 'raw_data', 'requirements', 'follow_ups'];
            $showBd = ($user?->isBusinessDevelopment() || $user?->isManager() || $user?->isSuperAdmin())
                && collect($bdModules)->contains(fn ($module) => $user->hasPermission($module));
        @endphp
        @if ($showBd)
            <div class="nav-section-title">Business Development</div>
            @permitted('leads')
                <a href="{{ route('leads.index') }}" class="nav-link {{ request()->routeIs('leads.*') ? 'active' : '' }}" title="Lead Management" data-bs-toggle="tooltip" data-bs-placement="right">
                    <i class="bi bi-diagram-3"></i> <span class="nav-label">Lead Management</span>
                </a>
            @endpermitted
            @permitted('raw_data')
                <a href="{{ route('raw-data.index') }}" class="nav-link {{ request()->routeIs('raw-data.*') ? 'active' : '' }} {{ isset($overdue['raw_data']) ? 'nav-overdue' : '' }}" title="Raw Data{{ isset($overdue['raw_data']) ? ' · '.$overdue['raw_data'].' overdue' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right">
                    <i class="bi bi-inbox"></i> <span class="nav-label">Raw Data</span>
                    @isset($overdue['raw_data'])<span class="nav-overdue-flag">overdue</span>@endisset
                </a>
            @endpermitted
            @permitted('requirements')
                <a href="{{ route('requirements.index') }}" class="nav-link {{ request()->routeIs('requirements.*') ? 'active' : '' }} {{ isset($overdue['requirements']) ? 'nav-overdue' : '' }}" title="Requirements{{ isset($overdue['requirements']) ? ' · '.$overdue['requirements'].' overdue' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right">
                    <i class="bi bi-list-check"></i> <span class="nav-label">Requirements</span>
                    @isset($overdue['requirements'])<span class="nav-overdue-flag">overdue</span>@endisset
                </a>
            @endpermitted
            @permitted('follow_ups')
                <a href="{{ route('follow-ups.index') }}" class="nav-link {{ request()->routeIs('follow-ups.*') ? 'active' : '' }} {{ isset($overdue['follow_ups']) ? 'nav-overdue' : '' }}" title="Follow Ups{{ isset($overdue['follow_ups']) ? ' · '.$overdue['follow_ups'].' overdue' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right">
                    <i class="bi bi-bell"></i> <span class="nav-label">Follow Ups</span>
                    @isset($overdue['follow_ups'])<span class="nav-overdue-flag">overdue</span>@endisset
                </a>
            @endpermitted
        @endif

        @permitted('support_tickets')
            <div class="nav-section-title">Support</div>
            <a href="{{ route('support-tickets.index') }}" class="nav-link {{ request()->routeIs('support-tickets.*') ? 'active' : '' }}" title="Support Tickets" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-life-preserver"></i> <span class="nav-label">Support Tickets</span>
            </a>
        @endpermitted

        @if (collect(['knowledge_base', 'release_notes', 'announcements'])->contains(fn ($module) => $user?->hasPermission($module)))
            <div class="nav-section-title">Knowledge</div>
        @endif
        @permitted('knowledge_base')
            <a href="{{ route('knowledge-base.index') }}" class="nav-link {{ request()->routeIs('knowledge-base.*') ? 'active' : '' }}" title="Knowledge Base" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-journal-richtext"></i> <span class="nav-label">Knowledge Base</span>
            </a>
        @endpermitted
        @permitted('release_notes')
            <a href="{{ route('release-notes.index') }}" class="nav-link {{ request()->routeIs('release-notes.*') ? 'active' : '' }}" title="Release Notes" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-megaphone"></i> <span class="nav-label">Release Notes</span>
            </a>
        @endpermitted
        @permitted('announcements')
            <a href="{{ route('announcements.index') }}" class="nav-link {{ request()->routeIs('announcements.*') ? 'active' : '' }}" title="Announcements" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-broadcast"></i> <span class="nav-label">Announcements</span>
            </a>
        @endpermitted

        @php
            $showCampaigns = $user?->can('viewAny', App\Models\Campaign::class) && $user->hasPermission('campaigns');
            $showContacts = $user?->can('viewAny', App\Models\Contact::class) && $user->hasPermission('contacts');
        @endphp
        @if ($showCampaigns || $showContacts)
            <div class="nav-section-title">Outreach</div>
        @endif
        @if ($showContacts)
            <a href="{{ route('contacts.index') }}" class="nav-link {{ request()->routeIs('contacts.*') ? 'active' : '' }}" title="Contacts" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-person-lines-fill"></i> <span class="nav-label">Contacts</span>
            </a>
        @endif
        @if ($showCampaigns)
            <a href="{{ route('campaigns.index') }}" class="nav-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}" title="Campaigns" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-send"></i> <span class="nav-label">Campaigns</span>
            </a>
        @endif

        <div class="nav-section-title">Reporting</div>
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
            <a href="{{ route('industries.index') }}" class="nav-link {{ request()->routeIs('industries.*') ? 'active' : '' }}" title="Industries" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-buildings"></i> <span class="nav-label">Industries</span>
            </a>
            <a href="{{ route('system-modules.index') }}" class="nav-link {{ request()->routeIs('system-modules.*') ? 'active' : '' }}" title="System Modules" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-grid-3x3-gap"></i> <span class="nav-label">System Modules</span>
            </a>
            <a href="{{ route('settings.edit') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" title="Settings" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-gear"></i> <span class="nav-label">Settings</span>
            </a>
            <a href="{{ route('campaign-setup.edit') }}" class="nav-link {{ request()->routeIs('campaign-setup.*') ? 'active' : '' }}" title="Campaign Setup" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-sliders"></i> <span class="nav-label">Campaign Setup</span>
            </a>
            <a href="{{ route('email-templates.index') }}" class="nav-link {{ request()->routeIs('email-templates.*') ? 'active' : '' }}" title="Email Templates" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-file-earmark-text"></i> <span class="nav-label">Email Templates</span>
            </a>
            <a href="{{ route('email-logs.index') }}" class="nav-link {{ request()->routeIs('email-logs.*') ? 'active' : '' }}" title="Email Log" data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi bi-envelope-paper"></i> <span class="nav-label">Email Log</span>
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
