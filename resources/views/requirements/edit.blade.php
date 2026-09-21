@extends('layouts.app')

@section('title', 'Edit Requirement')

@section('content')
    <x-page-header title="Edit Requirement" icon="bi-list-check" :subtitle="$requirement->lead?->company_name">
        <x-slot:actions>
            <a href="{{ route('requirements.show', $requirement) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye"></i> View
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="small text-muted">
                Created by <strong>{{ $requirement->creator?->name ?? 'Unknown' }}</strong> on {{ $requirement->created_at->format('M d, Y g:i A') }}
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('requirements.update', $requirement) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', $requirement->title) }}" maxlength="255">
                        @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <x-rich-text-editor name="requirement" label="Requirement" :value="old('requirement', $requirement->requirement)" required placeholder="Describe the requirement..." />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected(old('priority', $requirement->priority->value) === $priority->value)>{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $requirement->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="{{ old('due_date', $requirement->due_date?->toDateString()) }}">
                        @error('due_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Client Acknowledged</label>
                        <input type="datetime-local" name="client_acknowledged_at" class="form-control" value="{{ old('client_acknowledged_at', $requirement->client_acknowledged_at?->format('Y-m-d\TH:i')) }}">
                        @error('client_acknowledged_at')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Assign To</label>
                        <select name="assigned_to" class="form-select" data-select2-field>
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('assigned_to', $requirement->assigned_to) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Sprint</label>
                        <select name="sprint" class="form-select" data-select2-field>
                            <option value="">Unscheduled</option>
                            @foreach ($sprints as $sprint)
                                <option value="{{ $sprint }}" @selected(old('sprint', $requirement->sprint) === $sprint)>{{ $sprint }}</option>
                            @endforeach
                        </select>
                        @error('sprint')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Add Attachments</label>
                        <input type="file" name="attachments[]" multiple class="form-control" accept=".pdf,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.gif,.webp">
                        <div class="form-text">PDF, Word (.docx), Excel, CSV, or a screenshot image.</div>
                        @error('attachments.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Requirement</button>
                    @if ($requirement->lead)
                        <a href="{{ route('leads.show', $requirement->lead) }}" class="btn btn-outline-secondary">Cancel</a>
                    @else
                        <a href="{{ route('requirements.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-paperclip"></i> Attachments ({{ $requirement->attachments->count() }})</div>
        <div class="card-body">
            @forelse ($requirement->attachments as $attachment)
                <a href="{{ route('requirement-attachments.download', $attachment) }}" class="badge bg-light text-dark border text-decoration-none me-1 mb-1">
                    <i class="bi bi-paperclip"></i> {{ $attachment->original_name }}
                </a>
            @empty
                <p class="text-muted small mb-0">No attachments yet.</p>
            @endforelse
        </div>
    </div>

    @include('requirements._change_log')
@endsection
