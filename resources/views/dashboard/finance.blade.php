@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Finance Dashboard" icon="bi-speedometer2" subtitle="Organization goals and activity." />

    <x-role-playbook :user="$user" :playbook="$playbook" :quote="$quote" />

    <x-resolution-time-banner :stats="[
        ['icon' => 'bi-life-preserver', 'label' => 'Avg. Support Ticket Solving Time', 'value' => $avgSupportTicketResolutionTime],
        ['icon' => 'bi-clipboard-check', 'label' => 'Avg. Requirement Solving Time', 'value' => $avgRequirementResolutionTime],
    ]" />

    <div class="row g-3 mb-0">
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
