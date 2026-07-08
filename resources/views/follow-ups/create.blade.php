@extends('layouts.app')

@section('title', 'Schedule Follow-up')

@section('content')
    <x-page-header title="Schedule Follow-up" icon="bi-bell" subtitle="Create a new follow-up reminder for a lead." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('follow-ups.store') }}">
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
                        <label class="form-label small fw-semibold">Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control" value="{{ old('follow_up_date') }}" required>
                        @error('follow_up_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Follow-up Time</label>
                        <input type="time" name="follow_up_time" class="form-control" value="{{ old('follow_up_time') }}" required>
                        @error('follow_up_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Reminder Type</label>
                        <select name="reminder_type" class="form-select">
                            @foreach ($reminderTypes as $type)
                                <option value="{{ $type->value }}" @selected(old('reminder_type') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('reminder_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Remind (minutes before)</label>
                        <input type="number" min="1" name="reminder_minutes_before" class="form-control" value="{{ old('reminder_minutes_before', 30) }}" required>
                        @error('reminder_minutes_before')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Schedule Follow-up</button>
                    <a href="{{ route('follow-ups.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
