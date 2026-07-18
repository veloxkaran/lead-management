@extends('layouts.app')

@section('title', 'Edit Implementation Request')

@section('content')
    <x-page-header title="Implementation Request" icon="bi-box-arrow-in-up-right" :subtitle="$implementationRequest->lead?->company_name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('implementation-requests.update', $implementationRequest) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', $implementationRequest->title) }}" required>
                        @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Details</label>
                        <textarea name="details" rows="4" class="form-control">{{ old('details', $implementationRequest->details) }}</textarea>
                        @error('details')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $implementationRequest->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Assigned Engineer</label>
                        <select name="assigned_to" class="form-select" data-select2>
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('assigned_to', $implementationRequest->assigned_to) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Planned Date</label>
                        <input type="date" name="planned_date" class="form-control" value="{{ old('planned_date', $implementationRequest->planned_date?->toDateString()) }}">
                        @error('planned_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Current Phase</label>
                        <input type="text" name="phase" class="form-control" value="{{ old('phase', $implementationRequest->phase) }}" placeholder="e.g. Data Migration">
                        @error('phase')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Completion %</label>
                        <input type="number" name="completion_percentage" class="form-control" min="0" max="100" value="{{ old('completion_percentage', $implementationRequest->completion_percentage) }}">
                        @error('completion_percentage')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Implementation Notes</label>
                        <textarea name="notes" rows="3" class="form-control">{{ old('notes', $implementationRequest->notes) }}</textarea>
                        @error('notes')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Request</button>
                    @if ($implementationRequest->lead)
                        <a href="{{ route('leads.show', $implementationRequest->lead) }}" class="btn btn-outline-secondary">Cancel</a>
                    @else
                        <a href="{{ route('implementation-requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
@endsection
