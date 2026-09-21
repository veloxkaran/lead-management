@extends('layouts.app')

@section('title', 'Requirements')

@section('content')
    <x-page-header title="Requirements" icon="bi-list-check" subtitle="Track client requirements across all leads.">
        <x-slot:actions>
            @can('create', App\Models\Requirement::class)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                    <i class="bi bi-plus-lg"></i> Add Requirement
                </button>
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
                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2-field>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Priority</label>
                    <select name="priority" class="form-select form-select-sm" data-select2-field>
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Sprint</label>
                    <select name="sprint" class="form-select form-select-sm" data-select2-field>
                        <option value="">All sprints</option>
                        @foreach ($sprints as $sprint)
                            <option value="{{ $sprint }}" @selected(($filters['sprint'] ?? null) === $sprint)>{{ $sprint }}</option>
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
                        <th>Company</th>
                        <th>Status</th>
                        <th>Requirements</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $lead)
                        <tr>
                            <td class="fw-semibold">{{ $lead->company_name }}</td>
                            <td><x-status-badge :status="App\Enums\CompanyRequirementStatus::fromRequirements($lead->requirements)" /></td>
                            <td>{{ $lead->requirements->count() }}</td>
                            <td class="text-end">
                                <a href="{{ route('requirements.company', $lead) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-list-check"></i> View Requirements
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state icon="bi-list-check" title="No requirements found" description="Try adjusting your filters or add a new requirement." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($companies->hasPages())
            <div class="card-footer bg-white">
                {{ $companies->links() }}
            </div>
        @endif
    </div>

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
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Assign To</label>
                                <select name="assigned_to" class="form-select form-select-sm" data-select2-field>
                                    <option value="">Unassigned</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}" @selected(old('assigned_to') == $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Sprint</label>
                                <select name="sprint" class="form-select form-select-sm" data-select2-field>
                                    <option value="">Unscheduled</option>
                                    @foreach ($sprints as $sprint)
                                        <option value="{{ $sprint }}" @selected(old('sprint') === $sprint)>{{ $sprint }}</option>
                                    @endforeach
                                </select>
                                @error('sprint')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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
