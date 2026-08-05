@extends('layouts.app')

@section('title', 'Support Tickets')

@section('content')
    <x-page-header title="Support Tickets" icon="bi-life-preserver" subtitle="Raised by Managers, worked by Customer Success.">
        <x-slot:actions>
            @can('create', App\Models\SupportTicket::class)
                <a href="{{ route('support-tickets.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Raise Ticket
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Company</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by company" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Priority</label>
                    <select name="priority" class="form-select form-select-sm" data-select2>
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('support-tickets.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Subject</th>
                        <th>Client</th>
                        <th>Raised By</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Generated Time</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td class="small fw-semibold"><a href="{{ route('support-tickets.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                            <td class="small">{{ $ticket->lead?->company_name ?? '—' }}</td>
                            <td class="small text-muted">{{ $ticket->raiser?->name }}</td>
                            <td class="small">{{ $ticket->assignee?->name ?? '—' }}</td>
                            <td><x-status-badge :status="$ticket->priority" /></td>
                            <td><x-status-badge :status="$ticket->status" /></td>
                            <td class="small">
                                @if ($ticket->resolved_at)
                                    <span class="text-success">Solved in {{ $ticket->elapsedMinutes() }} min</span>
                                @else
                                    <span x-data="ticketElapsed('{{ $ticket->created_at->toIso8601String() }}')" x-text="text">{{ $ticket->elapsedMinutes() }} min</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('support-tickets.show', $ticket) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                @can('update', $ticket)
                                    <a href="{{ route('support-tickets.edit', $ticket) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $ticket)
                                    <form method="POST" action="{{ route('support-tickets.destroy', $ticket) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this ticket?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="bi-life-preserver" title="No support tickets found" description="Try adjusting your filters or raise a new ticket." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())
            <div class="card-footer bg-white">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
@endsection
