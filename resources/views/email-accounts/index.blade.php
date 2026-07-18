@extends('layouts.app')

@section('title', 'Email Accounts')

@section('content')
    <x-page-header title="Email Accounts" icon="bi-envelope-at" subtitle="Manage your own email account configurations.">
        <x-slot:actions>
            @can('create', App\Models\EmailAccount::class)
                <a href="{{ route('email-accounts.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Email Account
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Email Address</th>
                        <th>Provider</th>
                        <th>Connection</th>
                        <th>Last Tested</th>
                        <th>Default</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $account->email_address }}</div>
                                @if ($account->display_name)
                                    <div class="small text-muted">{{ $account->display_name }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $account->provider->label() }}</td>
                            <td>
                                <x-status-badge :status="$account->connection_status" />
                                @if ($account->connection_status->value === 'failed' && $account->connection_error)
                                    <div class="small text-danger mt-1">{{ Str::limit($account->connection_error, 60) }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $account->last_tested_at?->diffForHumans() ?? 'Never' }}</td>
                            <td>
                                @if ($account->is_default)
                                    <span class="badge bg-primary">Default</span>
                                @else
                                    <form method="POST" action="{{ route('email-accounts.set-default', $account) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-link p-0">Set as default</button>
                                    </form>
                                @endif
                            </td>
                            <td>
                                <form method="PATCH" action="{{ route('email-accounts.toggle-active', $account) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm {{ $account->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                        {{ $account->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('email-accounts.test-connection', $account) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plug"></i> Test</button>
                                </form>
                                <a href="{{ route('email-accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('email-accounts.destroy', $account) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this email account?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state icon="bi-envelope-at" title="No email accounts configured yet" description="Add your first email account to get started." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($accounts->hasPages())
            <div class="card-footer bg-white">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
@endsection
