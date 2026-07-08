@extends('layouts.app')

@section('title', 'Activity Feed')

@section('content')
    <x-page-header title="Activity Feed" icon="bi-clock-history" subtitle="Every activity logged across leads." />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Type</label>
                    <select name="activity_type" class="form-select form-select-sm" data-select2>
                        <option value="">All types</option>
                        @foreach ($activityTypes as $type)
                            <option value="{{ $type->value }}" @selected(($filters['activity_type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('activities.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>Lead</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Created By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        <tr>
                            <td class="small text-muted">
                                {{ $activity->activity_date->format('M d, Y') }}
                                {{ \Illuminate\Support\Carbon::parse($activity->activity_time)->format('g:i A') }}
                            </td>
                            <td>
                                @if ($activity->lead)
                                    <a href="{{ route('leads.show', $activity->lead) }}" class="text-decoration-none fw-semibold">{{ $activity->lead->company_name }}</a>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="small"><i class="bi {{ $activity->activity_type->icon() }} me-1"></i>{{ $activity->activity_type->label() }}</td>
                            <td class="small">{{ $activity->description }}</td>
                            <td class="small text-muted">{{ $activity->creator?->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state icon="bi-clock-history" title="No activities found" description="Try adjusting your filters." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($activities->hasPages())
            <div class="card-footer bg-white">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
@endsection
