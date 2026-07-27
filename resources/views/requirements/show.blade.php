@extends('layouts.app')

@section('title', 'Requirement')

@section('content')
    <x-page-header title="Requirement" icon="bi-list-check" :subtitle="$requirement->lead?->company_name">
        <x-slot:actions>
            @can('update', $requirement)
                <a href="{{ route('requirements.edit', $requirement) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endcan
            <a href="{{ route('requirements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <div class="small text-muted">Requirement</div>
                    <p class="mb-0">{{ $requirement->requirement }}</p>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Priority</div>
                    <x-status-badge :status="$requirement->priority" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Status</div>
                    <x-status-badge :status="$requirement->status" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Due Date</div>
                    <div class="small fw-semibold">
                        {{ $requirement->due_date?->format('M d, Y') ?? '—' }}
                        @if ($requirement->due_date && $requirement->due_date->isPast() && $requirement->status->value !== 'completed')
                            <span class="badge bg-danger-subtle text-danger-emphasis">Overdue</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Assigned To</div>
                    <div class="small fw-semibold">{{ $requirement->assignee?->name ?? 'Unassigned' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Client Acknowledged</div>
                    <div class="small fw-semibold">
                        @if ($requirement->isAcknowledgedByClient())
                            <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> {{ $requirement->client_acknowledged_at->format('M d, Y g:i A') }}</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Not yet</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Created By</div>
                    <div class="small fw-semibold">{{ $requirement->creator?->name ?? 'Unknown' }} on {{ $requirement->created_at->format('M d, Y g:i A') }}</div>
                </div>
            </div>
        </div>
    </div>

    @include('requirements._comments')
    @include('requirements._change_log')
@endsection
