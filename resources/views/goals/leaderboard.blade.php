@extends('layouts.app')

@section('title', 'Goals Leaderboard')

@section('content')
    <x-page-header title="Goals Leaderboard" icon="bi-trophy" subtitle="Contributors ranked by what they've closed, company-wide." />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Goal</label>
                    <select name="goal_id" class="form-select form-select-sm">
                        <option value="">All goals</option>
                        @foreach ($goals as $goal)
                            <option value="{{ $goal->id }}" @selected(($filters['goal_id'] ?? null) == $goal->id)>{{ $goal->title }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($companies)
                    <div class="col-md-2">
                        <label class="form-label small">Company</label>
                        <select name="company_id" class="form-select form-select-sm">
                            <option value="">All companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected(($filters['company_id'] ?? null) == $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2">
                    <label class="form-label small">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 form-check mb-1">
                    <input type="checkbox" name="my_team" value="1" id="my_team" class="form-check-input" @checked($filters['my_team'] ?? false)>
                    <label class="form-check-label small" for="my_team">My Team only</label>
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Rank</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Total Contribution</th>
                        <th>Deals Closed</th>
                        <th>Contribution %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td class="fw-semibold">{{ ($rows->currentPage() - 1) * $rows->perPage() + $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $row->user->name }}</div>
                                <div class="text-muted small">{{ $row->user->email }}</div>
                            </td>
                            <td class="small">{{ $row->user->designation ?? '—' }}</td>
                            <td><x-currency :amount="$row->total_amount" /></td>
                            <td>{{ $row->deals_count }}</td>
                            <td class="small">{{ $total > 0 ? round($row->total_amount / $total * 100, 1) : 0 }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-trophy" title="No contributions yet" description="Contributions are recorded automatically when a deal is closed." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="card-footer bg-white">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
