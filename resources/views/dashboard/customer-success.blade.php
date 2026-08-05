@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Customer Success Dashboard" icon="bi-speedometer2" subtitle="Support tickets and organization goals." />

    <x-role-playbook :user="$user" :playbook="$playbook" :quote="$quote" />

    <x-resolution-time-banner :stats="[
        ['icon' => 'bi-life-preserver', 'label' => 'Avg. Support Ticket Solving Time', 'value' => $avgSupportTicketResolutionTime],
        ['icon' => 'bi-clipboard-check', 'label' => 'Avg. Requirement Solving Time', 'value' => $avgRequirementResolutionTime],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center gap-3">
                <span class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-life-preserver"></i></span>
                <div><div class="fs-4 fw-semibold">{{ $pendingTickets }}</div><div class="text-muted small">Pending Support Tickets</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-life-preserver me-1"></i> Support Tickets</span>
                    <a href="{{ route('support-tickets.index') }}" class="small">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($ticketQueue as $ticket)
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('support-tickets.edit', $ticket) }}" class="text-decoration-none fw-semibold">{{ $ticket->subject }}</a>
                                <x-status-badge :status="$ticket->priority" />
                            </div>
                            <div class="text-muted" style="font-size:0.72rem;">Raised by {{ $ticket->raiser?->name }} · <x-status-badge :status="$ticket->status" /></div>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-life-preserver" title="No open tickets" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0 mb-0">
        <div class="col-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-bullseye me-1"></i> Organization Goals</span>
                    <a href="{{ route('goals.index') }}" class="small">View all</a>
                </div>
                <div class="card-body">
                    @forelse ($organizationGoals as $goal)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ $goal->title }}</span>
                                <span>{{ $goal->achievementPercentage() }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="width: {{ $goal->achievementPercentage() }}%"></div>
                            </div>
                            <div class="small text-muted mt-1"><x-currency :amount="$goal->achieved" /> / <x-currency :amount="$goal->target" /></div>
                        </div>
                    @empty
                        <x-empty-state icon="bi-bullseye" title="No organization goals set" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-12">
            <x-activity-feed-widget />
        </div>
    </div>
@endsection
