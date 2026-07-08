@extends('layouts.app')

@section('title', 'Lead Statuses')

@section('content')
    <x-page-header title="Lead Statuses" icon="bi-tags" subtitle="Manage the pipeline stages leads move through.">
        <x-slot:actions>
            <a href="{{ route('lead-statuses.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Add Status
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        @if ($statuses->isEmpty())
            <div class="card-body">
                <x-empty-state icon="bi-tags" title="No lead statuses yet" description="Create your first pipeline stage." />
            </div>
        @else
            <form method="POST" action="{{ route('lead-statuses.reorder') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 90px;">Order</th>
                                <th>Color</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Flags</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statuses as $status)
                                <tr>
                                    <td>
                                        <input type="number" name="order[{{ $status->id }}]" value="{{ $status->order }}" class="form-control form-control-sm" style="width: 70px;">
                                    </td>
                                    <td>
                                        <span class="d-inline-block rounded" style="width: 1.25rem; height: 1.25rem; background-color: {{ $status->color }}; vertical-align: middle;" title="{{ $status->color }}"></span>
                                    </td>
                                    <td class="fw-semibold">{{ $status->name }}</td>
                                    <td class="text-muted small"><code>{{ $status->slug }}</code></td>
                                    <td>
                                        @if ($status->is_default)
                                            <span class="badge bg-primary">Default</span>
                                        @endif
                                        @if ($status->is_closed_won)
                                            <span class="badge bg-success">Closed Won</span>
                                        @endif
                                        @if ($status->is_closed_lost)
                                            <span class="badge bg-danger">Closed Lost</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('lead-statuses.edit', $status) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                        <form action="{{ route('lead-statuses.destroy', $status) }}" method="POST" class="d-inline"
                                            data-confirm-delete
                                            data-confirm-title="Delete lead status?"
                                            data-confirm-text="This cannot be undone.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-down-up"></i> Save Order</button>
                </div>
            </form>
        @endif
    </div>
@endsection
