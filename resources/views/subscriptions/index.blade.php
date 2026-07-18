@extends('layouts.app')

@section('title', 'Subscriptions')

@section('content')
    <x-page-header title="Subscriptions" icon="bi-credit-card" subtitle="Client plans, billing, and renewals.">
        <x-slot:actions>
            @can('create', App\Models\Subscription::class)
                <a href="{{ route('subscriptions.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Subscription
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('subscriptions.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Client</th>
                        <th>Plan</th>
                        <th>Expiry</th>
                        <th>Renewal Amount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptions as $subscription)
                        <tr>
                            <td>
                                @if ($subscription->lead)
                                    <a href="{{ route('leads.show', $subscription->lead) }}" class="text-decoration-none fw-semibold">{{ $subscription->lead->company_name }}</a>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="small">{{ $subscription->plan_name }}</td>
                            <td class="small">
                                {{ $subscription->expiry_date?->format('M d, Y') ?? '—' }}
                                @if ($subscription->isExpiringSoon())
                                    <span class="badge bg-warning text-dark ms-1"><i class="bi bi-exclamation-triangle"></i> Expiring Soon</span>
                                @endif
                            </td>
                            <td class="small"><x-currency :amount="$subscription->renewal_amount" /></td>
                            <td><x-status-badge :status="$subscription->status" /></td>
                            <td class="text-end">
                                @can('update', $subscription)
                                    <a href="{{ route('subscriptions.edit', $subscription) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $subscription)
                                    <form method="POST" action="{{ route('subscriptions.destroy', $subscription) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this subscription?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-credit-card" title="No subscriptions found" description="Try adjusting your filters or add a new subscription." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($subscriptions->hasPages())
            <div class="card-footer bg-white">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>
@endsection
