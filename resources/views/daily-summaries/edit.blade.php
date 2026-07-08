@extends('layouts.app')

@section('title', 'Edit Daily Summary')

@section('content')
    <x-page-header title="Edit Daily Summary" icon="bi-journal-text" :subtitle="$dailySummary->summary_date->format('M d, Y')" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('daily-summaries.update', $dailySummary) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Date *</label>
                        <input type="date" name="summary_date" value="{{ old('summary_date', $dailySummary->summary_date->format('Y-m-d')) }}" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">What did you achieve today? *</label>
                        <textarea name="achieved_today" rows="4" class="form-control" required>{{ old('achieved_today', $dailySummary->achieved_today) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">What will you achieve tomorrow? *</label>
                        <textarea name="planned_tomorrow" rows="4" class="form-control" required>{{ old('planned_tomorrow', $dailySummary->planned_tomorrow) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Any blockers?</label>
                        <textarea name="blockers" rows="3" class="form-control">{{ old('blockers', $dailySummary->blockers) }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Summary</button>
                    <a href="{{ route('daily-summaries.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
