@extends('layouts.app')

@section('title', 'Requirements')

@section('content')
    <x-page-header title="Requirements" icon="bi-list-check" subtitle="Track client requirements across all leads.">
        <x-slot:actions>
            @can('create', App\Models\Requirement::class)
                <a href="{{ route('requirements.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Requirement
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
                    <a href="{{ route('requirements.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <a href="{{ route('requirements.export-pdf', $filters) }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Lead</th>
                        <th>Requirement</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Client Acknowledged</th>
                        <th>Assigned To</th>
                        <th>Sprint</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td>
                                @if ($requirement->lead)
                                    <a href="{{ route('leads.show', $requirement->lead) }}" class="text-decoration-none fw-semibold">{{ $requirement->lead->company_name }}</a>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="small">
                                <a href="{{ route('requirements.show', $requirement) }}" class="text-decoration-none">{{ $requirement->requirement }}</a>
                                @if ($requirement->comments_count)
                                    <span class="text-muted"><i class="bi bi-chat-left-text"></i> {{ $requirement->comments_count }}</span>
                                @endif
                            </td>
                            <td><x-status-badge :status="$requirement->priority" /></td>
                            <td><x-status-badge :status="$requirement->status" /></td>
                            <td class="small">
                                {{ $requirement->due_date?->format('M d, Y') ?? '—' }}
                                @if ($requirement->due_date && $requirement->due_date->isPast() && $requirement->status->value !== 'completed')
                                    <span class="badge bg-danger-subtle text-danger-emphasis">Overdue</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($requirement->isAcknowledgedByClient())
                                    <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> {{ $requirement->client_acknowledged_at->format('M d, Y g:i A') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Not yet</span>
                                @endif
                            </td>
                            <td class="small">{{ $requirement->assignee?->name ?? '—' }}</td>
                            <td class="small">{{ $requirement->sprint ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('requirements.show', $requirement) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                @can('update', $requirement)
                                    <a href="{{ route('requirements.edit', $requirement) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $requirement)
                                    <form method="POST" action="{{ route('requirements.destroy', $requirement) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this requirement?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-empty-state icon="bi-list-check" title="No requirements found" description="Try adjusting your filters or add a new requirement." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requirements->hasPages())
            <div class="card-footer bg-white">
                {{ $requirements->links() }}
            </div>
        @endif
    </div>
@endsection
