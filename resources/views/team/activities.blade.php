@extends('layouts.app')

@section('title', 'Team Activities')

@section('content')
    <x-page-header title="Team Activities" icon="bi-clock-history" subtitle="Everything your team has done, in one chronological feed.">
        <x-slot:actions>
            <a href="{{ route('team.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-people-fill"></i> Back to My Team
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" placeholder="Description or employee">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Employee</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Everyone</option>
                        @foreach ($filterableUsers as $filterableUser)
                            <option value="{{ $filterableUser->id }}" @selected(($filters['user_id'] ?? null) == $filterableUser->id)>{{ $filterableUser->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Activity Type</label>
                    <select name="module" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach ($modules as $definition)
                            <option value="{{ $definition->module->value }}" @selected(($filters['module'] ?? null) === $definition->module->value)>{{ $definition->label }}</option>
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
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('team.activities') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                        <th>Activity Type</th>
                        <th>Description</th>
                        <th>Related Client/Lead</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        @php
                            $link = \App\Support\ActivityLinkResolver::resolve($entry, auth()->user());
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $entry->user->name }}</div>
                                <div class="text-muted small">{{ $entry->user->email }}</div>
                            </td>
                            <td class="small">
                                <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi {{ $entry->module->icon() }}"></i> {{ $entry->module->label() }}</span>
                            </td>
                            <td class="small">{{ $entry->description }}</td>
                            <td class="small">
                                @if ($link['can_view'] && $link['url'])
                                    <a href="{{ $link['url'] }}" class="text-decoration-none">View</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $entry->created_at->format('M d, Y g:i A') }}
                                <div>{{ $entry->created_at->diffForHumans() }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="bi-clock-history" title="No activity yet" description="Nothing has been logged for your team in this range yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())
            <div class="card-footer bg-white">{{ $entries->links() }}</div>
        @endif
    </div>
@endsection
