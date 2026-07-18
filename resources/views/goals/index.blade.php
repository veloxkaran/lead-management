@extends('layouts.app')

@section('title', 'Goals')

@section('content')
    <x-page-header title="Goals" icon="bi-bullseye" subtitle="Organizational targets every employee contributes toward.">
        <x-slot:actions>
            <a href="{{ route('goals.leaderboard') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-trophy"></i> Leaderboard
            </a>
            @can('create', App\Models\Goal::class)
                <a href="{{ route('goals.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Goal
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All categories</option>
                        @foreach (\App\Enums\GoalCategory::cases() as $category)
                            <option value="{{ $category->value }}" @selected(($filters['category'] ?? null) === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach (\App\Enums\GoalStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('goals.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Period</th>
                        <th style="width: 220px;">Progress</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($goals as $goal)
                        <tr>
                            <td class="fw-semibold"><a href="{{ route('goals.show', $goal) }}" class="text-decoration-none">{{ $goal->title }}</a></td>
                            <td><span class="badge bg-secondary">{{ $goal->category->label() }}</span></td>
                            <td><x-status-badge :status="$goal->status()" /></td>
                            <td class="small text-muted">
                                {{ $goal->start_date?->format('M d, Y') }} &ndash; {{ $goal->end_date?->format('M d, Y') }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $goal->achievementPercentage() }}%;"></div>
                                    </div>
                                    <span class="small text-muted">{{ $goal->achievementPercentage() }}%</span>
                                </div>
                                <div class="small text-muted mt-1"><x-currency :amount="$goal->achieved" /> / <x-currency :amount="$goal->target" /></div>
                            </td>
                            <td class="text-end">
                                @can('update', $goal)
                                    <a href="{{ route('goals.edit', $goal) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $goal)
                                    <form action="{{ route('goals.destroy', $goal) }}" method="POST" class="d-inline"
                                        data-confirm-delete
                                        data-confirm-title="Delete goal?"
                                        data-confirm-text="This cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-bullseye" title="No goals found" description="No goals match the current filters." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($goals->hasPages())
            <div class="card-footer bg-white">
                {{ $goals->links() }}
            </div>
        @endif
    </div>
@endsection
