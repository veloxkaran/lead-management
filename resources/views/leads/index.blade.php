@extends('layouts.app')

@section('title', 'Lead Management')

@section('content')
    <x-page-header title="Lead Management" icon="bi-diagram-3" subtitle="Track and manage every prospective client.">
        <x-slot:actions>
            <a href="{{ route('leads.index', array_merge($filters, ['archived' => 1])) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-archive"></i> {{ !empty($filters['archived']) ? 'Active Leads' : 'Archived Leads' }}
            </a>
            @can('create', App\Models\Lead::class)
                <a href="{{ route('leads.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Lead
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                @if (!empty($filters['archived']))
                    <input type="hidden" name="archived" value="1">
                @endif
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" placeholder="Company, contact, email">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Company Name</label>
                    <input type="text" name="company_name" value="{{ $filters['company_name'] ?? '' }}" class="form-control form-control-sm" placeholder="Company name">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status_id" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected(($filters['status_id'] ?? null) == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Assigned To</label>
                    <select name="assigned_user_id" class="form-select form-select-sm">
                        <option value="">Everyone</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(($filters['assigned_user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Created By</label>
                    <select name="created_by" class="form-select form-select-sm">
                        <option value="">Everyone</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(($filters['created_by'] ?? '') == $u->id)>{{ $u->id === auth()->id() ? 'Me' : $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Time in Status</th>
                        <th>Assigned To</th>
                        <th>Activity</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leads as $lead)
                        <tr>
                            <td>
                                <a href="{{ route('leads.show', $lead) }}" class="fw-semibold text-decoration-none">{{ $lead->company_name }}</a>
                                <div class="text-muted small">{{ $lead->industry }}</div>
                            </td>
                            <td>
                                {{ $lead->contact_person }}
                                <div class="text-muted small">{{ $lead->email }}</div>
                            </td>
                            <td>
                                @if ($lead->status)
                                    <span class="badge" style="background-color: {{ $lead->status->color }};">{{ $lead->status->name }}</span>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $lead->currentStatusAge() }}</td>
                            <td>{{ $lead->assignedUser?->name ?? '—' }}</td>
                            <td class="small text-muted">
                                {{ $lead->activities_count }} activities · {{ $lead->notes_count }} notes · {{ $lead->requirements_count }} reqs
                            </td>
                            <td class="small text-muted">{{ $lead->created_at->format('M d, Y') }}</td>
                            <td class="text-end">
                                <a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                @can('update', $lead)
                                    <a href="{{ route('leads.edit', $lead) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('archive', $lead)
                                    <form action="{{ route(!empty($filters['archived']) ? 'leads.restore' : 'leads.archive', $lead) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" title="{{ !empty($filters['archived']) ? 'Restore' : 'Archive' }}">
                                            <i class="bi {{ !empty($filters['archived']) ? 'bi-arrow-counterclockwise' : 'bi-archive' }}"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="bi-diagram-3" title="No leads found" description="Try adjusting your filters or add a new lead." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($leads->hasPages())
            <div class="card-footer bg-white">
                {{ $leads->links() }}
            </div>
        @endif
    </div>
@endsection
