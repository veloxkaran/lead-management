@extends('layouts.app')

@section('title', 'New Task')

@section('content')
    <x-page-header title="New Task" icon="bi-list-task" subtitle="Create a task and assign it within your reporting chain." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('tasks.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                        @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description') }}</textarea>
                        @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Module</label>
                        <select name="module" class="form-select" required>
                            @foreach ($modules as $module)
                                <option value="{{ $module->value }}" @selected(old('module') === $module->value)>{{ $module->label() }}</option>
                            @endforeach
                        </select>
                        @error('module')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Client (optional)</label>
                        <select name="lead_id" class="form-select" data-select2>
                            <option value="">—</option>
                            @foreach ($leads as $lead)
                                <option value="{{ $lead->id }}" @selected(old('lead_id') == $lead->id)>{{ $lead->company_name }}</option>
                            @endforeach
                        </select>
                        @error('lead_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected(old('priority') === $priority->value)>{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Assigned To</label>
                        <select name="assigned_to" class="form-select" data-select2>
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('assigned_to') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                        @error('due_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Estimated Hours</label>
                        <input type="number" step="0.5" min="0" name="estimated_hours" class="form-control" value="{{ old('estimated_hours') }}">
                        @error('estimated_hours')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Create Task</button>
                    <a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
