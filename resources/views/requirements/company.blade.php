@extends('layouts.app')

@section('title', $lead->company_name.' — Requirements')

@section('content')
    <x-page-header :title="$lead->company_name" icon="bi-list-check" :subtitle="'Requirements for this company'.($lead->assignedUser ? ' · Lead owner: '.$lead->assignedUser->name : '').'.'">
        <x-slot:actions>
            <x-status-badge :status="App\Enums\CompanyRequirementStatus::fromRequirements($requirements)" />
            <a href="{{ route('requirements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> All Requirements
            </a>
            @can('create', App\Models\Requirement::class)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                    <i class="bi bi-plus-lg"></i> Add Requirement
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php
        $listItems = $requirements->mapWithKeys(fn ($requirement) => [$requirement->id => [
            'completed' => $requirement->status === App\Enums\RequirementStatus::Completed,
            'overdue' => $requirement->isOverdue(),
            'text' => Str::lower(implode(' ', [$requirement->summary(500), $requirement->assignee?->name])),
        ]]);
    @endphp

    <div class="card border-0 shadow-sm" x-data="requirementList(@js($listItems))">
        @if ($requirements->isNotEmpty())
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Filter requirements">
                    @foreach (['all' => ['All', $summary->total], 'open' => ['Open', $summary->open], 'overdue' => ['Overdue', $summary->overdue], 'completed' => ['Completed', $summary->completed]] as $key => [$label, $count])
                        <button type="button" class="btn" :class="view === '{{ $key }}' ? 'btn-primary' : 'btn-outline-secondary'" @click="view = '{{ $key }}'">
                            {{ $label }} <span class="badge rounded-pill ms-1" :class="view === '{{ $key }}' ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary-emphasis'">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="input-group input-group-sm ms-md-auto" style="max-width: 280px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" placeholder="Search requirements or assignee" x-model.debounce.150ms="q">
                </div>
                <div class="d-flex align-items-center gap-3 small text-muted">
                    @include('requirements._progress', ['summary' => $summary])
                    <span title="Average time from generated to solved" class="text-nowrap"><i class="bi bi-stopwatch"></i> {{ $summary->avgSolvingTime ?? 'No completed yet' }}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggleAll()">
                    <i class="bi" :class="allExpanded ? 'bi-arrows-collapse' : 'bi-arrows-expand'"></i>
                    <span x-text="allExpanded ? 'Collapse all' : 'Expand all'">Expand all</span>
                </button>
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Requirement</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Client Acknowledged</th>
                        <th>Assigned To</th>
                        <th>Generated / Solved</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requirements as $requirement)
                        <tr x-show="visible({{ $requirement->id }})">
                            <td class="small">
                                <div class="d-flex align-items-start gap-1">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" @click="toggle({{ $requirement->id }})" :aria-expanded="isExpanded({{ $requirement->id }})" title="Show full requirement">
                                        <i class="bi" :class="isExpanded({{ $requirement->id }}) ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                                    </button>
                                    <div>
                                        <a href="{{ route('requirements.show', $requirement) }}" class="text-decoration-none fw-semibold">{{ $requirement->summary(80) }}</a>
                                        <div class="text-muted">
                                            @if ($requirement->systemModule)
                                                <span class="me-2" title="Module"><i class="bi bi-grid-3x3-gap"></i> {{ $requirement->systemModule->name }}</span>
                                            @endif
                                            @if ($requirement->comments_count)
                                                <span class="me-2"><i class="bi bi-chat-left-text"></i> {{ $requirement->comments_count }}</span>
                                            @endif
                                            @if ($requirement->attachments->isNotEmpty())
                                                <span><i class="bi bi-paperclip"></i> {{ $requirement->attachments->count() }}</span>
                                            @endif
                                        </div>
                                    </div>
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
                            <td class="small">
                                @if ($requirement->isAcknowledgedByClient())
                                    <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> {{ $requirement->client_acknowledged_at->format('M d, Y g:i A') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Not yet</span>
                                @endif
                            </td>
                            <td class="small">{{ $requirement->assignee?->name ?? '—' }}</td>
                            <td>@include('requirements._timing')</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('requirements.show', $requirement) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                @can('changeStatus', $requirement)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal-{{ $requirement->id }}" title="Change status &amp; add note">
                                        <i class="bi bi-chat-square-text"></i>
                                    </button>
                                @endcan
                                @can('update', $requirement)
                                    <a href="{{ route('requirements.edit', $requirement) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $requirement)
                                    <form method="POST" action="{{ route('requirements.destroy', $requirement) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this requirement?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                        <tr x-show="visible({{ $requirement->id }}) && isExpanded({{ $requirement->id }})" x-cloak class="table-light">
                            <td colspan="8" class="px-4 py-3">
                                @include('requirements._details')
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="bi-list-check" title="No requirements yet" description="Add the first requirement for this company." />
                            </td>
                        </tr>
                    @endforelse
                    @if ($requirements->isNotEmpty())
                        <tr x-show="visibleCount === 0" x-cloak>
                            <td colspan="8">
                                <x-empty-state icon="bi-search" title="No requirements match" description="Try another search or pick a different filter." />
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            // Instant, client-side filter pills + search + expand/collapse for one company's requirements.
            window.requirementList = function (items) {
                return {
                    items,
                    view: 'all',
                    q: '',
                    expanded: [],

                    visible(id) {
                        const item = this.items[id];
                        const inView = this.view === 'all'
                            || (this.view === 'open' && ! item.completed)
                            || (this.view === 'overdue' && item.overdue)
                            || (this.view === 'completed' && item.completed);

                        return inView && item.text.includes(this.q.trim().toLowerCase());
                    },

                    get visibleIds() {
                        return Object.keys(this.items).map(Number).filter(id => this.visible(id));
                    },

                    get visibleCount() {
                        return this.visibleIds.length;
                    },

                    isExpanded(id) {
                        return this.expanded.includes(id);
                    },

                    toggle(id) {
                        this.expanded = this.isExpanded(id) ? this.expanded.filter(i => i !== id) : [...this.expanded, id];
                    },

                    get allExpanded() {
                        return this.visibleCount > 0 && this.visibleIds.every(id => this.isExpanded(id));
                    },

                    toggleAll() {
                        const ids = this.visibleIds;
                        this.expanded = this.allExpanded
                            ? this.expanded.filter(i => ! ids.includes(i))
                            : [...new Set([...this.expanded, ...ids])];
                    },
                };
            };
        </script>
    @endpush

    @include('requirements._status_modals')

    @can('create', App\Models\Requirement::class)
        <div class="modal fade" id="addRequirementModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('leads.requirements.store', $lead) }}" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Requirement — {{ $lead->company_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
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
