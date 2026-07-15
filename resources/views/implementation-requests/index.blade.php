@extends('layouts.app')

@section('title', 'Implementation Requests')

@section('content')
    <x-page-header title="Implementation Requests" icon="bi-box-arrow-in-up-right" subtitle="Handed off from Business Development to Customer Success on Closed-Won.">
        <x-slot:actions>
            @can('create', App\Models\ImplementationRequest::class)
                <a href="{{ route('implementation-requests.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Raise Request
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
                    <a href="{{ route('implementation-requests.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                        <th>Request</th>
                        <th>Requested By</th>
                        <th>Assigned To</th>
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
                            <td class="small">{{ $req->title }}</td>
                            <td class="small text-muted">{{ $req->requester?->name }}</td>
                            <td class="small">{{ $req->assignee?->name ?? '—' }}</td>
                            <td><x-status-badge :status="$req->status" /></td>
                            <td class="text-end">
                                @can('update', $req)
                                    <a href="{{ route('implementation-requests.edit', $req) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $req)
                                    <form method="POST" action="{{ route('implementation-requests.destroy', $req) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this request?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-box-arrow-in-up-right" title="No implementation requests found" description="Try adjusting your filters or raise a new request." />
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
