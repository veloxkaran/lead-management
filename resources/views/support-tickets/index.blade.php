@extends('layouts.app')

@section('title', 'Support Tickets')

@section('content')
    <x-page-header title="Support Tickets" icon="bi-life-preserver" subtitle="Raised by Managers, worked by Customer Success.">
        <x-slot:actions>
            @can('create', App\Models\SupportTicket::class)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#raiseTicketModal">
                    <i class="bi bi-plus-lg"></i> Raise Ticket
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end" x-data="{ period: '{{ $filters['period'] ?? '' }}' }">
                <div class="col-md-3">
                    <label class="form-label small">Company</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by company" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2-field>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Priority</label>
                    <select name="priority" class="form-select form-select-sm" data-select2-field>
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Generated</label>
                    <select name="period" class="form-select form-select-sm" x-model="period">
                        <option value="">Any time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3" x-show="period === 'custom'" x-cloak>
                    <label class="form-label small">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3" x-show="period === 'custom'" x-cloak>
                    <label class="form-label small">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
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
                            <td class="small">
                                @if ($ticket->lead)
                                    <a href="{{ route('leads.show', $ticket->lead) }}" class="text-decoration-none">{{ $ticket->lead->company_name }}</a>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="small text-muted">{{ $ticket->raiserDisplayName() }}</td>
                            <td class="small">{{ $ticket->assignee?->name ?? '—' }}</td>
                            <td><x-status-badge :status="$ticket->priority" /></td>
                            <td><x-status-badge :status="$ticket->status" /></td>
                            <td class="small">
                                @if ($ticket->resolved_at)
                                    <span class="text-success">Solved in {{ $ticket->elapsedFormatted() }}</span>
                                @else
                                    <span x-data="ticketElapsed('{{ $ticket->created_at->toIso8601String() }}')" x-text="text">{{ $ticket->elapsedFormatted() }}</span>
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

    @can('create', App\Models\SupportTicket::class)
        <div class="modal fade" id="raiseTicketModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('support-tickets.store') }}" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Raise Support Ticket</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Related Lead (optional)</label>
                                <select name="lead_id" class="form-select form-select-sm" data-select2-field>
                                    <option value=""></option>
                                    @foreach ($leads as $lead)
                                        <option value="{{ $lead->id }}" @selected(old('lead_id') == $lead->id)>{{ $lead->company_name }}</option>
                                    @endforeach
                                </select>
                                @error('lead_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Subject</label>
                                <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
                                @error('subject')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Details</label>
                                <textarea name="details" rows="4" class="form-control">{{ old('details') }}</textarea>
                                @error('details')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Priority</label>
                                <select name="priority" class="form-select form-select-sm">
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>
                                    @endforeach
                                </select>
                                @error('priority')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Assign To (optional)</label>
                                <select name="assigned_to" class="form-select form-select-sm" data-select2-field>
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}" @selected(old('assigned_to') == $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Documents (optional)</label>
                                <input type="file" name="attachments[]" multiple class="form-control">
                                @error('attachments.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Raise Ticket</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($errors->any())
            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        new bootstrap.Modal(document.getElementById('raiseTicketModal')).show();
                    });
                </script>
            @endpush
        @endif
    @endcan
@endsection
