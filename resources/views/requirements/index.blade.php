@extends('layouts.app')

@section('title', 'Requirements')

@section('content')
    <x-page-header title="Requirements" icon="bi-list-check" subtitle="Track client requirements across all leads.">
        <x-slot:actions>
            @can('create', App\Models\Requirement::class)
                <a href="{{ route('requirements.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Requirement
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Company</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by company" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Priority</label>
                    <select name="priority" class="form-select form-select-sm" data-select2>
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Sprint</label>
                    <select name="sprint" class="form-select form-select-sm" data-select2>
                        <option value="">All sprints</option>
                        @foreach ($sprints as $sprint)
                            <option value="{{ $sprint }}" @selected(($filters['sprint'] ?? null) === $sprint)>{{ $sprint }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('requirements.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <a href="{{ route('requirements.export-pdf', $filters) }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Requirements</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $lead)
                        <tr>
                            <td class="fw-semibold">{{ $lead->company_name }}</td>
                            <td><x-status-badge :status="App\Enums\CompanyRequirementStatus::fromRequirements($lead->requirements)" /></td>
                            <td>{{ $lead->requirements->count() }}</td>
                            <td class="text-end">
                                <a href="{{ route('requirements.company', $lead) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-list-check"></i> View Requirements
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state icon="bi-list-check" title="No requirements found" description="Try adjusting your filters or add a new requirement." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($companies->hasPages())
            <div class="card-footer bg-white">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
@endsection
