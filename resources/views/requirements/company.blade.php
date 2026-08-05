@extends('layouts.app')

@section('title', $lead->company_name.' — Requirements')

@section('content')
    <x-page-header :title="$lead->company_name" icon="bi-list-check" subtitle="Requirements for this company.">
        <x-slot:actions>
            <x-status-badge :status="App\Enums\CompanyRequirementStatus::fromRequirements($requirements)" />
            <a href="{{ route('requirements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Companies
            </a>
            @can('create', App\Models\Requirement::class)
                <a href="{{ route('requirements.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Requirement
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Requirement</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Client Acknowledged</th>
                        <th>Assigned To</th>
                        <th>Sprint</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td class="small">
                                <a href="{{ route('requirements.show', $requirement) }}" class="text-decoration-none">{{ $requirement->requirement }}</a>
                                @if ($requirement->comments_count)
                                    <span class="text-muted"><i class="bi bi-chat-left-text"></i> {{ $requirement->comments_count }}</span>
                                @endif
                            </td>
                            <td><x-status-badge :status="$requirement->priority" /></td>
                            <td><x-status-badge :status="$requirement->status" /></td>
                            <td class="small">
                                {{ $requirement->due_date?->format('M d, Y') ?? '—' }}
                                @if ($requirement->due_date && $requirement->due_date->isPast() && $requirement->status->value !== 'completed')
                                    <span class="badge bg-danger-subtle text-danger-emphasis">Overdue</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($requirement->isAcknowledgedByClient())
                                    <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> {{ $requirement->client_acknowledged_at->format('M d, Y g:i A') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Not yet</span>
                                @endif
                            </td>
                            <td class="small">{{ $requirement->assignee?->name ?? '—' }}</td>
                            <td class="small">{{ $requirement->sprint ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('requirements.show', $requirement) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                @can('update', $requirement)
                                    <a href="{{ route('requirements.edit', $requirement) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $requirement)
                                    <form method="POST" action="{{ route('requirements.destroy', $requirement) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this requirement?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="bi-list-check" title="No requirements yet" description="Add the first requirement for this company." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
