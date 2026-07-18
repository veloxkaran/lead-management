@extends('layouts.app')

@section('title', 'Edit Training')

@section('content')
    <x-page-header title="Training" icon="bi-mortarboard" :subtitle="$training->lead?->company_name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('trainings.update', $training) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $training->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Training Date</label>
                        <input type="date" name="training_date" class="form-control" value="{{ old('training_date', $training->training_date?->toDateString()) }}">
                        @error('training_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Trainer Name</label>
                        <input type="text" name="trainer_name" class="form-control" value="{{ old('trainer_name', $training->trainer_name) }}">
                        @error('trainer_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Attendees</label>
                        <input type="number" name="attendees_count" class="form-control" min="0" value="{{ old('attendees_count', $training->attendees_count) }}">
                        @error('attendees_count')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Completion %</label>
                        <input type="number" name="completion_percentage" class="form-control" min="0" max="100" value="{{ old('completion_percentage', $training->completion_percentage) }}">
                        @error('completion_percentage')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Training Feedback</label>
                        <textarea name="feedback" rows="3" class="form-control">{{ old('feedback', $training->feedback) }}</textarea>
                        @error('feedback')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Training</button>
                    @if ($training->lead)
                        <a href="{{ route('leads.show', $training->lead) }}" class="btn btn-outline-secondary">Cancel</a>
                    @else
                        <a href="{{ route('trainings.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
@endsection
