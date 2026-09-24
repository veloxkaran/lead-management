@extends('layouts.app')

@section('title', $lead->company_name.' — Requirements')

@section('content')
    <x-page-header :title="$lead->company_name" icon="bi-list-check" subtitle="Requirements for this company.">
        <x-slot:actions>
            <x-status-badge :status="App\Enums\CompanyRequirementStatus::fromRequirements($requirements)" />
            <a href="{{ route('requirements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Companies
            </a>
            @can('create', App\Models\Requirement::class)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                    <i class="bi bi-plus-lg"></i> Add Requirement
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
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
                        <th>Sprint</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td class="small">
                                <a href="{{ route('requirements.show', $requirement) }}" class="text-decoration-none">
                                    {{ $requirement->summary(80) }}
                                </a>
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
                                @can('changeStatus', $requirement)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal-{{ $requirement->id }}" title="Change status &amp; add note">
                                        <i class="bi bi-chat-square-text"></i>
                                    </button>
                                @endcan
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
                            <td colspan="8">
                                <x-empty-state icon="bi-list-check" title="No requirements yet" description="Add the first requirement for this company." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($requirements as $requirement)
        @can('changeStatus', $requirement)
            <div class="modal fade" id="statusModal-{{ $requirement->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('requirements.status.update', $requirement) }}" class="modal-content">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header">
                            <h5 class="modal-title">Change Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">{{ $requirement->summary(120) }}</p>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Status</label>
                                <select name="status" class="form-select" required>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @selected($requirement->status === $status)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Notes *</label>
                                <textarea name="note" rows="3" class="form-control" required placeholder="Explain why the status is changing"></textarea>
                                <div class="form-text">A note is required to change the status.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endforeach

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
