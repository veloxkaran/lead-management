@extends('layouts.app')

@section('title', 'Edit Requirement')

@section('content')
    <x-page-header title="Edit Requirement" icon="bi-list-check" :subtitle="$requirement->lead?->company_name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('requirements.update', $requirement) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Requirement</label>
                        <textarea name="requirement" rows="3" class="form-control" required>{{ old('requirement', $requirement->requirement) }}</textarea>
                        @error('requirement')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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
                        <select name="assigned_to" class="form-select" data-select2>
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('assigned_to', $requirement->assigned_to) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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
        <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history"></i> Change Log</div>
        <div class="card-body">
            @forelse ($changeLog as $entry)
                <div class="border-bottom pb-2 mb-2 small">
                    <span class="fw-semibold">{{ $entry->user?->name ?? 'Unknown' }}</span>
                    <span class="text-muted">{{ $entry->created_at->format('M d, Y g:i A') }}</span>
                    <ul class="mb-0 mt-1">
                        @foreach ($entry->new_values as $field => $newValue)
                            <li>
                                <strong>{{ \Illuminate\Support\Str::headline($field) }}:</strong>
                                {{ $entry->old_values[$field] ?? '—' }} &rarr; {{ $newValue ?? '—' }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="text-muted small mb-0">No changes logged yet.</p>
            @endforelse
        </div>
    </div>
@endsection
