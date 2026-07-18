@extends('layouts.app')

@section('title', 'Schedule Training')

@section('content')
    <x-page-header title="Schedule Training" icon="bi-mortarboard" subtitle="Schedule a training session for a client." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('trainings.store') }}">
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
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Training Date</label>
                        <input type="date" name="training_date" class="form-control" value="{{ old('training_date') }}">
                        @error('training_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Trainer Name</label>
                        <input type="text" name="trainer_name" class="form-control" value="{{ old('trainer_name') }}">
                        @error('trainer_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Attendees</label>
                        <input type="number" name="attendees_count" class="form-control" min="0" value="{{ old('attendees_count') }}">
                        @error('attendees_count')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Department Covered</label>
                        <select name="department_id" class="form-select" data-select2>
                            <option value="">—</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Schedule Training</button>
                    <a href="{{ route('trainings.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
