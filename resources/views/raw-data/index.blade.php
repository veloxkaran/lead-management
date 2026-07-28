@extends('layouts.app')

@section('title', 'Raw Data')

@section('content')
    <x-page-header title="Raw Data" icon="bi-inbox" subtitle="Minimal contact records — convert to a Lead once qualified.">
        <x-slot:actions>
            @can('create', App\Models\RawData::class)
                <a href="{{ route('raw-data.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Raw Data
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Contact person or phone" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2>
                        <option value="">All statuses</option>
                        @foreach (App\Enums\RawDataStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
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
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Comments</th>
                        <th>Added By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="small fw-semibold"><a href="{{ route('raw-data.show', $entry) }}">{{ $entry->contact_person }}</a></td>
                            <td class="small">{{ $entry->phone }}</td>
                            <td><x-status-badge :status="$entry->status" /></td>
                            <td class="small">{{ $entry->comments_count }}</td>
                            <td class="small text-muted">{{ $entry->creator?->name ?? '—' }}</td>
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
                            <td colspan="6">
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
