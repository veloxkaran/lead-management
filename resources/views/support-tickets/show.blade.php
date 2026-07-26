@extends('layouts.app')

@section('title', 'Support Ticket')

@section('content')
    <x-page-header title="{{ $supportTicket->subject }}" icon="bi-life-preserver" :subtitle="$supportTicket->lead?->company_name">
        <x-slot:actions>
            @can('update', $supportTicket)
                <a href="{{ route('support-tickets.edit', $supportTicket) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endcan
            <a href="{{ route('support-tickets.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="small text-muted">Status</div>
                    <x-status-badge :status="$supportTicket->status" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Priority</div>
                    <x-status-badge :status="$supportTicket->priority" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Raised By</div>
                    <div class="small fw-semibold">{{ $supportTicket->raiser?->name ?? 'Unknown' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Assigned To</div>
                    <div class="small fw-semibold">{{ $supportTicket->assignee?->name ?? 'Unassigned' }}</div>
                </div>
                <div class="col-12">
                    <div class="small text-muted">Details</div>
                    <p class="mb-0">{{ $supportTicket->details ?: 'No details provided.' }}</p>
                </div>
                <div class="col-12">
                    <div class="small text-muted">
                        Raised on {{ $supportTicket->created_at->format('M d, Y g:i A') }}
                        @unless ($supportTicket->detailsEditable())
                            &middot; <span class="fst-italic">Details are now locked (12-hour edit window has passed).</span>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('support-tickets._comments')
@endsection
