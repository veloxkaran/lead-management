@extends('layouts.app')

@section('title', 'Edit Follow-up')

@section('content')
    <x-page-header title="Edit Follow-up" icon="bi-bell" :subtitle="$followUp->lead?->company_name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('follow-ups.update', $followUp) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control" value="{{ old('follow_up_date', $followUp->follow_up_date->toDateString()) }}" required>
                        @error('follow_up_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Follow-up Time</label>
                        <input type="time" name="follow_up_time" class="form-control" value="{{ old('follow_up_time', \Illuminate\Support\Carbon::parse($followUp->follow_up_time)->format('H:i')) }}" required>
                        @error('follow_up_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Reminder Type</label>
                        <select name="reminder_type" class="form-select">
                            @foreach ($reminderTypes as $type)
                                <option value="{{ $type->value }}" @selected(old('reminder_type', $followUp->reminder_type->value) === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('reminder_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Remind (minutes before)</label>
                        <input type="number" min="1" name="reminder_minutes_before" class="form-control" value="{{ old('reminder_minutes_before', $followUp->reminder_minutes_before) }}" required>
                        @error('reminder_minutes_before')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $followUp->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Follow-up</button>
                    @if ($followUp->lead)
                        <a href="{{ route('leads.show', $followUp->lead) }}" class="btn btn-outline-secondary">Cancel</a>
                    @else
                        <a href="{{ route('follow-ups.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
@endsection
