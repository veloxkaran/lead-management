@extends('layouts.app')

@section('title', 'Account Requests')

@section('content')
    <x-page-header title="Account Requests" icon="bi-cash-coin" subtitle="Sent from Business Development to Finance for invoicing and payment.">
        <x-slot:actions>
            @can('create', App\Models\AccountRequest::class)
                <a href="{{ route('account-requests.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Send Request
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
                    <a href="{{ route('account-requests.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Requested By</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $req)
                        <tr>
                            <td>
                                @if ($req->lead)
                                    <a href="{{ route('leads.show', $req->lead) }}" class="text-decoration-none fw-semibold">{{ $req->lead->company_name }}</a>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td><x-status-badge :status="$req->request_type" /></td>
                            <td><x-currency :amount="$req->amount" /></td>
                            <td class="small text-muted">{{ $req->requester?->name }}</td>
                            <td><x-status-badge :status="$req->status" /></td>
                            <td class="text-end">
                                @can('update', $req)
                                    <a href="{{ route('account-requests.edit', $req) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $req)
                                    <form method="POST" action="{{ route('account-requests.destroy', $req) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this request?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-cash-coin" title="No account requests found" description="Try adjusting your filters or send a new request." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer bg-white">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
@endsection
