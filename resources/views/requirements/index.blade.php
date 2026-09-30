@extends('layouts.app')

@section('title', 'Requirements')

@section('content')
    <x-page-header title="Requirements" icon="bi-list-check" subtitle="Every client requirement, across all leads.">
        <x-slot:actions>
            @can('create', App\Models\Requirement::class)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                    <i class="bi bi-plus-lg"></i> Add Requirement
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php
        $activeFilterCount = count(array_filter(\Illuminate\Support\Arr::except($filters, ['view'])));
        $currentView = $filters['view'] ?? null;
        $views = [
            null => ['All', $summary->total, 'primary'],
            'open' => ['Open', $summary->open, 'info'],
            'overdue' => ['Overdue', $summary->overdue, 'danger'],
            'completed' => ['Completed', $summary->completed, 'success'],
        ];
    @endphp

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            {{-- Fixed-basis flex items so the bar keeps the same shape whether or not filters are active. --}}
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div style="flex: 1 1 220px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" class="form-control" placeholder="Search requirement or company" value="{{ $filters['search'] ?? '' }}">
                    </div>
                </div>
                <div class="filter-bar-item" style="--filter-basis: 200px; --filter-min: 160px;">
                    <select name="lead_id" class="form-select form-select-sm" data-select2-field aria-label="Company">
                        <option value="">All companies</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(($filters['lead_id'] ?? null) == $company->id)>{{ $company->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-bar-item" style="--filter-basis: 170px; --filter-min: 140px;">
                    <select name="system_module_id" class="form-select form-select-sm" data-select2-field aria-label="Module">
                        <option value="">All modules</option>
                        @foreach ($systemModules as $module)
                            <option value="{{ $module->id }}" @selected(($filters['system_module_id'] ?? null) == $module->id)>{{ $module->name }}</option>
                        @endforeach
                        <option value="_none" @selected(($filters['system_module_id'] ?? null) === '_none')>No module</option>
                    </select>
                </div>
                <div class="filter-bar-item" style="--filter-basis: 150px; --filter-min: 130px;">
                    <select name="status" class="form-select form-select-sm" data-select2-field aria-label="Status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-bar-item" style="--filter-basis: 130px; --filter-min: 115px;">
                    <select name="priority" class="form-select form-select-sm" data-select2-field aria-label="Priority">
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-check form-switch mb-0 flex-shrink-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="my_leads" value="1" id="myLeadsFilter" @checked(! empty($filters['my_leads'])) onchange="this.form.submit()">
                    <label class="form-check-label small text-nowrap" for="myLeadsFilter">My Leads</label>
                </div>
                @if ($currentView)
                    <input type="hidden" name="view" value="{{ $currentView }}">
                @endif
                <div class="d-flex gap-1 flex-shrink-0 ms-auto">
                    <button type="submit" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('requirements.index') }}" @class(['btn btn-sm btn-outline-secondary', 'disabled' => ! $activeFilterCount && ! $currentView]) @if (! $activeFilterCount && ! $currentView) aria-disabled="true" tabindex="-1" @endif title="Clear all filters"><i class="bi bi-x-lg"></i></a>
                    <a href="{{ route('requirements.export-pdf', $filters) }}" class="btn btn-sm btn-outline-secondary" title="Export PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2 py-2">
            <nav class="nav nav-pills small" aria-label="Quick views">
                @foreach ($views as $key => [$label, $count, $tone])
                    @php
                        $isActive = $currentView === ($key ?: null);
                    @endphp
                    <a href="{{ route('requirements.index', array_filter([...$filters, 'view' => $key ?: null])) }}"
                       @class(['nav-link py-1 px-2 d-flex align-items-center gap-1', 'active' => $isActive, 'text-body' => ! $isActive])
                       @if ($isActive) aria-current="page" @endif>
                        {{ $label }}
                        <span @class(['badge rounded-pill', 'bg-white text-primary' => $isActive, "bg-{$tone}-subtle text-{$tone}-emphasis" => ! $isActive])>{{ $count }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="ms-auto d-flex align-items-center gap-3 small text-muted">
                @if ($activeFilterCount)
                    <span class="text-primary" title="Filters narrowing this list"><i class="bi bi-funnel-fill"></i> {{ $activeFilterCount }} {{ Str::plural('filter', $activeFilterCount) }} active</span>
                @endif
                <span title="Completed out of total"><i class="bi bi-check2-circle"></i> {{ $summary->completionPercent() }}% done</span>
                <span title="Average time from generated to solved"><i class="bi bi-stopwatch"></i> {{ $summary->avgSolvingTime ?? 'No completed yet' }}</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small">
                        <th style="min-width: 280px;">Requirement</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Assigned To</th>
                        <th>Generated Time</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td>
                                <a href="{{ route('requirements.show', $requirement) }}" class="text-decoration-none fw-semibold text-body">{{ $requirement->summary(90) }}</a>
                                <div class="small text-muted d-flex flex-wrap gap-2">
                                    @if ($requirement->systemModule)
                                        <span title="Module"><i class="bi bi-grid-3x3-gap"></i> {{ $requirement->systemModule->name }}</span>
                                    @endif
                                    @if ($requirement->lead)
                                        <a href="{{ route('requirements.company', $requirement->lead) }}" class="text-decoration-none text-muted" title="All requirements for {{ $requirement->lead->company_name }}">
                                            <i class="bi bi-building"></i> {{ $requirement->lead->company_name }}
                                        </a>
                                    @endif
                                    @if ($requirement->comments_count)
                                        <span><i class="bi bi-chat-left-text"></i> {{ $requirement->comments_count }}</span>
                                    @endif
                                    @if ($requirement->attachments->isNotEmpty())
                                        <span><i class="bi bi-paperclip"></i> {{ $requirement->attachments->count() }}</span>
                                    @endif
                                </div>
                            </td>
                            <td><x-status-badge :status="$requirement->priority" /></td>
                            <td><x-status-badge :status="$requirement->status" /></td>
                            <td class="small text-nowrap">
                                {{ $requirement->due_date?->format('M d, Y') ?? '—' }}
                                @if ($requirement->isOverdue())
                                    <span class="badge bg-danger-subtle text-danger-emphasis">Overdue</span>
                                @endif
                            </td>
                            <td class="small">{{ $requirement->assignee?->name ?? '—' }}</td>
                            {{-- Same as the Support Tickets list's Generated Time column. --}}
                            <td class="small">
                                @if ($requirement->isCompleted())
                                    <span class="text-success fw-semibold">Solved in {{ $requirement->solvedInFormatted() ?? '—' }}</span>
                                @else
                                    <span x-data="ticketElapsed('{{ $requirement->created_at->toIso8601String() }}')" x-text="text">{{ $requirement->elapsedFormatted() }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('requirements.show', $requirement) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                @can('changeStatus', $requirement)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal-{{ $requirement->id }}" title="Change status &amp; add note">
                                        <i class="bi bi-chat-square-text"></i>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @if (! empty($filters['my_leads']) && $activeFilterCount === 1 && ! $currentView)
                                    <x-empty-state icon="bi-person-check" title="None of your leads have requirements" description="Turn off “My Leads” to see requirements across every lead." />
                                @else
                                    <x-empty-state icon="bi-list-check" title="No requirements found" description="Try adjusting your filters or add a new requirement." />
                                @endif
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

    @include('requirements._status_modals')

    @can('create', App\Models\Requirement::class)
        <div class="modal fade" id="addRequirementModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('requirements.store') }}" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Requirement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Lead</label>
                                <select name="lead_id" class="form-select form-select-sm" data-select2-field required>
                                    <option value=""></option>
                                    @foreach ($leads as $lead)
                                        <option value="{{ $lead->id }}" @selected(old('lead_id') == $lead->id)>{{ $lead->company_name }}</option>
                                    @endforeach
                                </select>
                                @error('lead_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <x-system-module-select :modules="$systemModules" />
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Title</label>
                                <input type="text" name="title" class="form-control" value="{{ old('title') }}" maxlength="255">
                                @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <x-rich-text-editor name="requirement" label="Requirement" :value="old('requirement')" required placeholder="Describe the requirement..." />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Priority</label>
                                <select name="priority" class="form-select form-select-sm">
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>
                                    @endforeach
                                </select>
                                @error('priority')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                                @error('due_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Client Acknowledged</label>
                                <input type="datetime-local" name="client_acknowledged_at" class="form-control" value="{{ old('client_acknowledged_at') }}">
                                @error('client_acknowledged_at')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Assign To</label>
                                <select name="assigned_to" class="form-select form-select-sm" data-select2-field>
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}" @selected(old('assigned_to') == $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Attachments (optional)</label>
                                <input type="file" name="attachments[]" multiple class="form-control" accept=".pdf,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.gif,.webp">
                                <div class="form-text">PDF, Word (.docx), Excel, CSV, or a screenshot image.</div>
                                @error('attachments.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Add Requirement</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($errors->any())
            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        new bootstrap.Modal(document.getElementById('addRequirementModal')).show();
                    });
                </script>
            @endpush
        @endif
    @endcan
@endsection
