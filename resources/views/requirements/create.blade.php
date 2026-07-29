@extends('layouts.app')

@section('title', 'Add Requirement')

@section('content')
    <x-page-header title="Add Requirement" icon="bi-list-check" subtitle="Log a new client requirement for a lead." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('requirements.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Lead</label>
                        <select name="lead_id" class="form-select" data-select2 required>
                            <option value=""></option>
                            @foreach ($leads as $lead)
                                <option value="{{ $lead->id }}" @selected(old('lead_id') == $lead->id)>{{ $lead->company_name }}</option>
                            @endforeach
                        </select>
                        @error('lead_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Requirement</label>
                        <textarea name="requirement" rows="3" class="form-control" required>{{ old('requirement') }}</textarea>
                        @error('requirement')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                        @error('due_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Client Acknowledged</label>
                        <input type="datetime-local" name="client_acknowledged_at" class="form-control" value="{{ old('client_acknowledged_at') }}">
                        @error('client_acknowledged_at')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Assign To</label>
                        <select name="assigned_to" class="form-select" data-select2>
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('assigned_to') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Adopted By</label>
                        <select name="adopted_by" class="form-select" data-select2>
                            <option value="">Not yet adopted</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('adopted_by') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('adopted_by')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Adopted At</label>
                        <input type="datetime-local" name="adopted_at" class="form-control" value="{{ old('adopted_at') }}">
                        @error('adopted_at')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Add Requirement</button>
                    <a href="{{ route('requirements.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
