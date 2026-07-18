@extends('layouts.app')

@section('title', 'Subscription History — '.$lead->company_name)

@section('content')
    <x-page-header title="Subscription History" icon="bi-credit-card" :subtitle="$lead->company_name">
        <x-slot:actions>
            <a href="{{ route('leads.show', $lead) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Lead</a>
            @can('create', App\Models\Subscription::class)
                <a href="{{ route('subscriptions.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Subscription</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Plan</th>
                        <th>Contract Start</th>
                        <th>Expiry</th>
                        <th>Renewal Amount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptions as $subscription)
                        <tr>
                            <td class="small fw-semibold">{{ $subscription->plan_name }}</td>
                            <td class="small">{{ $subscription->contract_start_date?->format('M d, Y') ?? '—' }}</td>
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
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-credit-card" title="No subscription history yet" />
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
