@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-page-header title="User Management" icon="bi-people" subtitle="Manage internal user accounts, roles, and access.">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add User</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" placeholder="Name or email">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Role</label>
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(($filters['role'] ?? null) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Name</th><th>Department</th><th>Role</th><th>Status</th><th>Team</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $user->name }}</div>
                                <div class="text-muted small">{{ $user->email }}</div>
                            </td>
                            <td class="small">{{ $user->department }} @if($user->designation) <div class="text-muted">{{ $user->designation }}</div> @endif</td>
                            <td><span class="badge bg-primary-subtle text-primary-emphasis">{{ $user->role->label() }}</span></td>
                            <td><x-status-badge :status="$user->status" /></td>
                            <td class="small">{{ $user->team?->name ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @can('impersonate', $user)
                                    <form action="{{ route('users.impersonate', $user) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary" title="Login as {{ $user->name }}"><i class="bi bi-box-arrow-in-right"></i></button>
                                    </form>
                                @endcan
                                <form action="{{ route('users.reset-password', $user) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Reset this user's password?" data-confirm-text="A new temporary password will be generated.">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" title="Reset password"><i class="bi bi-key"></i></button>
                                </form>
                                @if ($user->status->value === 'suspended')
                                    <form action="{{ route('users.activate', $user) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" title="Activate"><i class="bi bi-play-circle"></i></button>
                                    </form>
                                @else
                                    <form action="{{ route('users.suspend', $user) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Suspend this user?">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" title="Suspend"><i class="bi bi-pause-circle"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-people" title="No users found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer bg-white">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
