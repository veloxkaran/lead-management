@extends('layouts.app')

@section('title', 'Raw Data')

@section('content')
    <x-page-header title="Raw Data" icon="bi-inbox" subtitle="Minimal contact records — convert to a Lead once qualified.">
        <x-slot:actions>
            @can('deleteIncomplete', App\Models\RawData::class)
                <form method="POST" action="{{ route('raw-data.delete-incomplete') }}" class="d-inline"
                      data-confirm-delete
                      data-confirm-title="Delete incomplete raw data?"
                      data-confirm-text="This permanently deletes every entry with no phone and no email, plus every remaining entry whose phone or email matches an existing lead. This cannot be undone."
                      data-confirm-button-text="Delete Incomplete Entries">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i> Delete Incomplete Entries
                    </button>
                </form>
            @endcan
            @can('create', App\Models\RawData::class)
                <a href="{{ route('raw-data.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Raw Data
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end" x-data="{ period: '{{ $filters['period'] ?? '' }}' }">
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Contact person or phone" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2>
                        <option value="">All statuses</option>
                        @foreach (App\Enums\RawDataStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Created</label>
                    <select name="period" class="form-select form-select-sm" x-model="period">
                        <option value="">Any time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-2" x-show="period === 'custom'" x-cloak>
                    <label class="form-label small">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2" x-show="period === 'custom'" x-cloak>
                    <label class="form-label small">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('raw-data.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Contact / Company</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Matched Lead</th>
                        <th>Added By</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="small">
                                <div class="fw-semibold"><a href="{{ route('raw-data.show', $entry) }}">{{ $entry->contact_person }}</a></div>
                                <div class="text-muted small">{{ $entry->company_name ?? '—' }}</div>
                                <div class="text-muted small"><i class="bi bi-chat-left-text"></i> {{ $entry->comments_count }} comment{{ $entry->comments_count === 1 ? '' : 's' }}</div>
                            </td>
                            <td class="small">{{ $entry->phone }}</td>
                            <td class="small">{{ $entry->email ?? '—' }}</td>
                            <td><x-status-badge :status="$entry->status" /></td>
                            <td class="small">
                                @if ($entry->matched_lead)
                                    <a href="{{ route('leads.show', $entry->matched_lead) }}" class="badge text-decoration-none" style="background-color: {{ $entry->matched_lead->status->color ?? '#6c757d' }};" title="Phone/email also matches this Lead">
                                        {{ $entry->matched_lead->status->name ?? 'No Status' }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $entry->creator?->name ?? '—' }}</td>
                            <td class="small">{{ $entry->assignee?->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $entry->created_at->format('M d, Y') }}</td>
                            <td class="text-end">
                                <a href="{{ route('raw-data.show', $entry) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                @can('delete', $entry)
                                    <form method="POST" action="{{ route('raw-data.destroy', $entry) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this raw data entry?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-empty-state icon="bi-inbox" title="No raw data found" description="Try adjusting your filters or add a new entry." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())
            <div class="card-footer bg-white">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
@endsection
