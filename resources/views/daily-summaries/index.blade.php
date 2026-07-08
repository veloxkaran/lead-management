@extends('layouts.app')

@section('title', 'Daily Summaries')

@section('content')
    <x-page-header title="Daily Summaries" icon="bi-journal-text" subtitle="What was achieved, what's next, and any blockers.">
        <x-slot:actions>
            <a href="{{ route('daily-summaries.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Submit Summary
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search text...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                @if ($users->isNotEmpty())
                    <div class="col-md-3">
                        <label class="form-label small">User</label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">Everyone</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('daily-summaries.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        @if ($users->isNotEmpty())
                            <th>User</th>
                        @endif
                        <th>Achieved Today</th>
                        <th>Planned Tomorrow</th>
                        <th>Blockers</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summaries as $summary)
                        <tr>
                            <td class="fw-semibold">{{ $summary->summary_date->format('M d, Y') }}</td>
                            @if ($users->isNotEmpty())
                                <td>{{ $summary->user?->name ?? '—' }}</td>
                            @endif
                            <td class="small">{{ \Illuminate\Support\Str::limit($summary->achieved_today, 60) }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($summary->planned_tomorrow, 60) }}</td>
                            <td>
                                @if (!empty($summary->blockers))
                                    <span class="badge bg-danger" title="{{ $summary->blockers }}">Yes</span>
                                @else
                                    <span class="badge bg-success-subtle text-success-emphasis">None</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('update', $summary)
                                    <a href="{{ route('daily-summaries.edit', $summary) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $users->isNotEmpty() ? 6 : 5 }}">
                                <x-empty-state icon="bi-journal-text" title="No daily summaries found" description="Try adjusting your search or submit today's summary." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($summaries->hasPages())
            <div class="card-footer bg-white">
                {{ $summaries->links() }}
            </div>
        @endif
    </div>
@endsection
