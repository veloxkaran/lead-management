@extends('layouts.app')

@section('title', $goal->title)

@section('content')
    <x-page-header :title="$goal->title" icon="bi-bullseye" :subtitle="$goal->category->label()">
        <x-slot:actions>
            @can('update', $goal)
                <a href="{{ route('goals.edit', $goal) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endcan
            <a href="{{ route('goals.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> All Goals
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <x-status-badge :status="$goal->status()" />
                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">{{ $goal->category->label() }}</span>
                </div>
                <div class="small text-muted">{{ $goal->start_date->format('M d, Y') }} &ndash; {{ $goal->end_date->format('M d, Y') }}</div>
            </div>

            @if ($goal->description)
                <p class="text-muted">{{ $goal->description }}</p>
            @endif

            <div class="progress mb-2" style="height: 10px;">
                <div class="progress-bar" style="width: {{ $goal->achievementPercentage() }}%"></div>
            </div>
            <div class="row g-3 small">
                <div class="col-md-3">
                    <div class="text-muted">Target</div>
                    <div class="fw-semibold"><x-currency :amount="$goal->target" /></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Achieved</div>
                    <div class="fw-semibold"><x-currency :amount="$goal->achieved" /></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Remaining</div>
                    <div class="fw-semibold"><x-currency :amount="max(0, $goal->target - $goal->achieved)" /></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Progress</div>
                    <div class="fw-semibold">{{ $goal->achievementPercentage() }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i> Contributors</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Contribution Type</th>
                        <th>Client Name</th>
                        <th>Value</th>
                        <th>Date</th>
                        <th>% of Goal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contributions as $contribution)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $contribution->user->name }}</div>
                                <div class="text-muted small">{{ $contribution->user->email }}</div>
                            </td>
                            <td class="small">{{ $contribution->user->designation ?? '—' }}</td>
                            <td class="small">{{ $contribution->contribution_type->label() }}</td>
                            <td class="small">{{ $contribution->source?->lead?->company_name ?? '—' }}</td>
                            <td><x-currency :amount="$contribution->amount" /></td>
                            <td class="small text-muted">{{ $contribution->contributed_at->format('M d, Y') }}</td>
                            <td class="small">
                                @if ($goal->category->aggregatesByCount())
                                    {{ $goal->achieved > 0 ? round(100 / $goal->achieved, 1) : 0 }}%
                                @else
                                    {{ $goal->achieved > 0 ? round($contribution->amount / $goal->achieved * 100, 1) : 0 }}%
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-people" title="No contributions yet" description="Contributions are recorded automatically when a deal is closed." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($contributions->hasPages())
            <div class="card-footer bg-white">{{ $contributions->links() }}</div>
        @endif
    </div>
@endsection
