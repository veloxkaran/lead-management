@extends('layouts.app')

@section('title', 'Submit Daily Summary')

@section('content')
    <x-page-header title="Submit Daily Summary" icon="bi-journal-text" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('daily-summaries.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Date *</label>
                        <input type="date" name="summary_date" value="{{ old('summary_date', now()->format('Y-m-d')) }}" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">What did you achieve today? *</label>
                        <textarea name="achieved_today" rows="4" class="form-control" required>{{ old('achieved_today') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">What will you achieve tomorrow? *</label>
                        <textarea name="planned_tomorrow" rows="4" class="form-control" required>{{ old('planned_tomorrow') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Any blockers?</label>
                        <textarea name="blockers" rows="3" class="form-control">{{ old('blockers') }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Submit Summary</button>
                    <a href="{{ route('daily-summaries.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
