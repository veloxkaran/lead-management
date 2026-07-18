@extends('layouts.app')

@section('title', 'My Team')

@section('content')
    <x-page-header title="My Team" icon="bi-people-fill" subtitle="Your reporting hierarchy — direct and indirect team members." />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-person-badge me-1"></i> {{ auth()->user()->name }}
        </div>
        <div class="card-body row g-2">
            <div class="col-md-6">
                <div class="text-muted small">Designation</div>
                <div class="fw-semibold small">{{ auth()->user()->designation ?? '—' }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Team Size</div>
                <div class="fw-semibold small">{{ $members->total() }}</div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-10">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" placeholder="Name or email">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Status</th>
                        <th>Reported Today</th>
                        <th>Last Activity</th>
                        <th>Today's Summary</th>
                        <th>This Week</th>
                        <th>This Month</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php
                            $weekly = $weeklyPerformance->get($member->id);
                            $monthly = $monthlyPerformance->get($member->id);
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $member->name }}</div>
                                <div class="text-muted small">{{ $member->email }}</div>
                            </td>
                            <td class="small">{{ $member->designation ?? '—' }}</td>
                            <td><x-status-badge :status="$member->status" /></td>
                            <td>
                                @if ($reportedToday->has($member->id))
                                    <span class="badge bg-success-subtle text-success-emphasis">Yes</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">No</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $latestActivity->get($member->id)?->created_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="small">
                                {{ \Illuminate\Support\Str::limit($latestSummary->get($member->id)?->achieved_today, 60) ?: '—' }}
                            </td>
                            <td class="small">
                                {{ $weekly->deals ?? 0 }} deals
                                <div class="text-muted">{{ \App\Support\Currency::format($weekly->value ?? 0) }}</div>
                            </td>
                            <td class="small">
                                {{ $monthly->deals ?? 0 }} deals
                                <div class="text-muted">{{ \App\Support\Currency::format($monthly->value ?? 0) }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="bi-people" title="No team members yet" description="You don't have any direct or indirect reports assigned yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($members->hasPages())
            <div class="card-footer bg-white">{{ $members->links() }}</div>
        @endif
    </div>
@endsection
