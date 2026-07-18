@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" icon="bi-bar-chart-line">
        <x-slot:actions>
            <a href="{{ route('reports.export', array_merge(['type' => $type, 'format' => 'excel'], request()->query())) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a href="{{ route('reports.export', array_merge(['type' => $type, 'format' => 'csv'], request()->query())) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a>
            <a href="{{ route('reports.export', array_merge(['type' => $type, 'format' => 'pdf'], request()->query())) }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                @if ($type === 'daily')
                    <div class="col-md-3">
                        <label class="form-label small">Date</label>
                        <input type="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="form-control form-control-sm">
                    </div>
                @elseif ($type === 'monthly')
                    <div class="col-md-3">
                        <label class="form-label small">Month</label>
                        <input type="month" name="month" value="{{ request('month', now()->format('Y-m')) }}" class="form-control form-control-sm">
                    </div>
                @elseif ($type === 'quarterly')
                    <div class="col-md-2">
                        <label class="form-label small">Year</label>
                        <input type="number" name="year" value="{{ request('year', now()->year) }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Quarter</label>
                        <select name="quarter" class="form-select form-select-sm">
                            @foreach ([1,2,3,4] as $q)
                                <option value="{{ $q }}" @selected(request('quarter', ceil(now()->month/3)) == $q)>Q{{ $q }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($type === 'master')
                    <div class="col-md-2">
                        <label class="form-label small">User</label>
                        <select name="user_id" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Status</label>
                        <select name="status_id" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach ($statuses as $s)<option value="{{ $s->id }}" @selected(request('status_id') == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Company</label>
                        <input type="text" name="company" value="{{ request('company') }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">From</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">To</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
                    </div>
                @elseif ($type === 'deal')
                    <div class="col-md-3">
                        <label class="form-label small">From</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">To</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
                    </div>
                @elseif ($type === 'requirement')
                    <div class="col-md-3">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach (\App\Enums\RequirementStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Apply</button>
                </div>
            </form>
        </div>
    </div>

    @isset($summary)
        <div class="row g-3 mb-3">
            @foreach ($summary as $label => $value)
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="fs-5 fw-semibold">{{ is_numeric($value) ? number_format($value, is_float($value) ? 2 : 0) : $value }}</div>
                        <div class="text-muted small">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endisset

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ((array) $row as $value)
                                <td class="small">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($headings) }}"><x-empty-state icon="bi-bar-chart-line" title="No data for this report" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
