@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Finance Dashboard" icon="bi-speedometer2" subtitle="Account requests from Business Development, start to close." />

    <x-role-playbook :user="$user" :playbook="$playbook" :quote="$quote" />

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <span class="stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-hourglass-split"></i></span>
                <div><div class="fs-4 fw-semibold">{{ $pendingCount }}</div><div class="text-muted small">Pending Requests</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <span class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-cash-stack"></i></span>
                <div><div class="fs-4 fw-semibold"><x-currency :amount="$pendingAmount" :decimals="0" /></div><div class="text-muted small">Outstanding Amount</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></span>
                <div><div class="fs-4 fw-semibold"><x-currency :amount="$completedThisMonth" :decimals="0" /></div><div class="text-muted small">Processed This Month</div></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span><i class="bi bi-cash-coin me-1"></i> Account Request Queue</span>
            <a href="{{ route('account-requests.index') }}" class="small">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Client</th><th>Type</th><th>Amount</th><th>Requested by</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($requestQueue as $req)
                        <tr>
                            <td>{{ $req->lead?->company_name }}</td>
                            <td><x-status-badge :status="$req->request_type" /></td>
                            <td><x-currency :amount="$req->amount" /></td>
                            <td class="small text-muted">{{ $req->requester?->name }}</td>
                            <td><x-status-badge :status="$req->status" /></td>
                            <td class="text-end"><a href="{{ route('account-requests.edit', $req) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-cash-coin" title="Nothing waiting on you" description="No open account requests." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-12">
            <x-activity-feed-widget />
        </div>
    </div>
@endsection
