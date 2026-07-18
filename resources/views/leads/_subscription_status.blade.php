@php $subscription = $lead->latestSubscription; @endphp
<div class="card border-0 shadow-sm mb-3 @if ($subscription?->isExpiringSoon()) border-start border-warning border-3 @endif">
    <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-credit-card"></i> Subscription Status</h6>
        @if ($subscription)
            <div class="d-flex justify-content-between align-items-center mb-2">
                <x-status-badge :status="$subscription->status" />
                @if ($subscription->isExpiringSoon())
                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Expiring in {{ $subscription->daysRemaining() }}d</span>
                @endif
            </div>
            <dl class="row small mb-0">
                <dt class="col-6 text-muted">Plan</dt><dd class="col-6">{{ $subscription->plan_name }}</dd>
                <dt class="col-6 text-muted">Billing Cycle</dt><dd class="col-6">{{ $subscription->billing_cycle?->label() ?? '—' }}</dd>
                <dt class="col-6 text-muted">Contract Start</dt><dd class="col-6">{{ $subscription->contract_start_date?->format('M d, Y') ?? '—' }}</dd>
                <dt class="col-6 text-muted">Expiry / Renewal</dt><dd class="col-6">{{ $subscription->expiry_date?->format('M d, Y') ?? '—' }}</dd>
                <dt class="col-6 text-muted">Days Remaining</dt><dd class="col-6">{{ $subscription->daysRemaining() ?? '—' }}</dd>
                <dt class="col-6 text-muted">Licensed / Active Users</dt><dd class="col-6">{{ $subscription->licensed_users ?? '—' }} / {{ $subscription->active_users ?? '—' }}</dd>
                <dt class="col-6 text-muted">Renewal Amount</dt><dd class="col-6"><x-currency :amount="$subscription->renewal_amount" /></dd>
                <dt class="col-6 text-muted">Auto Renewal</dt><dd class="col-6">{{ $subscription->auto_renew ? 'Enabled' : 'Disabled' }}</dd>
                <dt class="col-6 text-muted">Last Payment</dt><dd class="col-6">{{ $subscription->last_payment_date?->format('M d, Y') ?? '—' }}</dd>
                <dt class="col-6 text-muted">Outstanding</dt>
                <dd class="col-6">
                    <x-currency :amount="$subscription->outstanding_amount" />
                    @if ($subscription->outstanding_amount > 0)
                        <span class="badge bg-danger-subtle text-danger-emphasis ms-1">Due</span>
                    @endif
                </dd>
            </dl>
        @else
            <x-empty-state icon="bi-credit-card" title="No subscription on record" />
        @endif
    </div>
    <div class="card-footer bg-white">
        <a href="{{ route('leads.subscriptions.index', $lead) }}" class="btn btn-sm btn-outline-primary w-100">View Subscription Details</a>
    </div>
</div>
