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

    @php
        $currentView = $filters['view'] ?? null;
        $activeFilterCount = count(array_filter(\Illuminate\Support\Arr::except($filters, ['view'])));
        $overdueRule = collect(App\Enums\RequirementPriority::cases())
            ->map(fn ($p) => $p->label().' '.App\Models\SupportTicket::overdueAfterHours($p).'h')
            ->implode(', ');
        $views = [
            null => ['All', $summary->total, 'primary', null],
            'open' => ['Open', $summary->open, 'info', 'Not completed or cancelled yet'],
            'overdue' => ['Overdue', $summary->overdue, 'danger', "Open longer than its priority allows — {$overdueRule}"],
            'completed' => ['Completed', $summary->completed, 'success', null],
            'cancelled' => ['Cancelled', $summary->cancelled, 'danger', null],
        ];
    @endphp

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end" x-data="{ period: '{{ $filters['period'] ?? '' }}' }">
                @if ($currentView)
                    <input type="hidden" name="view" value="{{ $currentView }}">
                @endif
                <div class="col-12 col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="search" name="search" class="form-control form-control-sm" placeholder="Subject or company" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach (App\Enums\SupportTicketType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                        <option value="none" @selected(($filters['type'] ?? null) === 'none')>Not set</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2-field>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small">Priority</label>
                    <select name="priority" class="form-select form-select-sm" data-select2-field>
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small">Raised By</label>
                    <select name="raised_by" class="form-select form-select-sm" data-select2-field>
                        <option value="">Anyone</option>
                        <option value="client" @selected(($filters['raised_by'] ?? null) === 'client')>Client (support portal)</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(($filters['raised_by'] ?? null) == $u->id)>{{ $u->id === auth()->id() ? 'Me' : $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small">Assigned To</label>
                    <select name="assigned_to" class="form-select form-select-sm" data-select2-field>
                        <option value="">Anyone</option>
                        <option value="unassigned" @selected(($filters['assigned_to'] ?? null) === 'unassigned')>Unassigned</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(($filters['assigned_to'] ?? null) == $u->id)>{{ $u->id === auth()->id() ? 'Me' : $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
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
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2 py-2">
            <nav class="nav nav-pills small" aria-label="Quick views">
                @foreach ($views as $key => [$label, $count, $tone, $hint])
                    @php $isActive = $currentView === ($key ?: null); @endphp
                    <a href="{{ route('support-tickets.index', array_filter([...$filters, 'view' => $key ?: null])) }}"
                       @class(['nav-link py-1 px-2 d-flex align-items-center gap-1', 'active' => $isActive, 'text-body' => ! $isActive])
                       @if ($hint) title="{{ $hint }}" @endif
                       @if ($isActive) aria-current="page" @endif>
                        {{ $label }}
                        <span @class(['badge rounded-pill', 'bg-white text-primary' => $isActive, "bg-{$tone}-subtle text-{$tone}-emphasis" => ! $isActive])>{{ $count }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="ms-auto d-flex align-items-center gap-3 small text-muted">
                @if ($activeFilterCount)
                    <span class="text-primary" title="Filters narrowing this list"><i class="bi bi-funnel-fill"></i> {{ $activeFilterCount }} {{ \Illuminate\Support\Str::plural('filter', $activeFilterCount) }} active</span>
                @endif
                <span title="Completed out of total"><i class="bi bi-check2-circle"></i> {{ $summary->completionPercent() }}% done</span>
                <span title="Average time from raised to resolved"><i class="bi bi-stopwatch"></i> {{ $summary->avgResolutionTime ?? 'None resolved yet' }}</span>
            </div>
        </div>
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
                            <td class="small">
                                <a href="{{ route('support-tickets.show', $ticket) }}" class="fw-semibold">{{ $ticket->subject }}</a>
                                <div class="mt-1">
                                    @if ($ticket->type)
                                        <x-status-badge :status="$ticket->type" />
                                    @else
                                        <span class="text-muted" style="font-size: .72rem;">Type not set</span>
                                    @endif
                                </div>
                            </td>
                            <td class="small">
                                @if ($ticket->lead)
                                    <a href="{{ route('leads.show', $ticket->lead) }}" class="text-decoration-none">{{ $ticket->lead->company_name }}</a>
                                    <div class="mt-1"><x-lead-status :lead="$ticket->lead" /></div>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="small text-muted">{{ $ticket->raiserDisplayName() }}</td>
                            <td class="small">{{ $ticket->assignee?->name ?? '—' }}</td>
                            <td><x-status-badge :status="$ticket->priority" /></td>
                            <td>
                                <x-status-badge :status="$ticket->status" />
                                <x-cancellation-reason :record="$ticket" />
                                @if ($ticket->isOverdue())
                                    <span class="badge bg-danger-subtle text-danger-emphasis" title="Open longer than {{ App\Models\SupportTicket::overdueAfterHours($ticket->priority) }}h for {{ strtolower($ticket->priority?->label() ?? 'this') }} priority">Overdue</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($ticket->resolved_at)
                                    <span class="text-success">Solved in {{ $ticket->elapsedFormatted() }}</span>
                                @elseif ($ticket->isCancelled())
                                    <span class="text-muted">Cancelled{{ $ticket->cancelled_at ? ' '.$ticket->cancelled_at->diffForHumans() : '' }}</span>
                                @else
                                    <span x-data="ticketElapsed('{{ $ticket->created_at->toIso8601String() }}')" x-text="text">{{ $ticket->elapsedFormatted() }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('support-tickets.show', $ticket) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                @can('changeStatus', $ticket)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ticketStatusModal-{{ $ticket->id }}" title="Change status &amp; add note">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                @endcan
                                @can('reopen', $ticket)
                                    <a href="{{ route('support-tickets.show', [$ticket, 'reopen' => 1]) }}" class="btn btn-sm btn-outline-warning" title="Reopen"><i class="bi bi-arrow-counterclockwise"></i></a>
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

    @include('support-tickets._status_modals')

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
                        <input type="hidden" name="_form" value="raise_ticket">
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
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Subject</label>
                                <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
                                @error('subject')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Type</label>
                                @include('support-tickets._type_select', ['selected' => old('type')])
                                @error('type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <x-rich-text-editor name="details" label="Details" :value="old('details')" placeholder="Describe the issue..." />
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

        {{-- Only for errors from this form — not e.g. a status popup on the same page. --}}
        @if ($errors->any() && old('_form') === 'raise_ticket')
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
