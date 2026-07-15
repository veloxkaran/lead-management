@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Manager Dashboard" icon="bi-speedometer2" subtitle="The full pipeline, across every department." />

    <x-role-playbook :user="$user" :playbook="$playbook" :quote="$quote" />

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-diagram-3"></i></span>
                <div><div class="fs-4 fw-semibold">{{ $totalLeads }}</div><div class="text-muted small">Active Leads</div></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-trophy"></i></span>
                <div><div class="fs-4 fw-semibold"><x-currency :amount="$dealStats['value']" :decimals="0" /></div><div class="text-muted small">Total Deal Value ({{ $dealStats['count'] }} deals)</div></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <span class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-box-arrow-in-up-right"></i></span>
                <div><div class="fs-4 fw-semibold">{{ $openImplementationRequests }}</div><div class="text-muted small">Open Implementation Requests</div></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <span class="stat-icon bg-info-subtle text-info"><i class="bi bi-life-preserver"></i></span>
                <div><div class="fs-4 fw-semibold">{{ $openSupportTickets }}</div><div class="text-muted small">Open Support Tickets</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Lead Status Distribution</div>
                <div class="card-body"><canvas id="statusChart" height="220"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Monthly Conversion</div>
                <div class="card-body"><canvas id="conversionChart" height="220"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-bullseye me-1"></i> Organization Goals</div>
                <div class="card-body">
                    @forelse ($organizationGoals as $goal)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1"><span>{{ $goal->title }}</span><span>{{ $goal->achievementPercentage() }}%</span></div>
                            <div class="progress" style="height: 8px;"><div class="progress-bar" style="width: {{ $goal->achievementPercentage() }}%"></div></div>
                            <div class="small text-muted mt-1"><x-currency :amount="$goal->achieved" /> / <x-currency :amount="$goal->target" /></div>
                        </div>
                    @empty
                        <x-empty-state icon="bi-bullseye" title="No organization goals set" />
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i> Team Goal Progress</div>
                <div class="card-body">
                    @forelse ($teamGoals as $goal)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1"><span>{{ $goal->team?->name }} — {{ $goal->title }}</span><span>{{ $goal->achievementPercentage() }}%</span></div>
                            <div class="progress" style="height: 8px;"><div class="progress-bar bg-info" style="width: {{ $goal->achievementPercentage() }}%"></div></div>
                            <div class="small text-muted mt-1"><x-currency :amount="$goal->achieved" /> / <x-currency :amount="$goal->target" /></div>
                        </div>
                    @empty
                        <x-empty-state icon="bi-people" title="No team goals set" />
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-camera-video me-1"></i> Team Meeting Schedule</div>
                <ul class="list-group list-group-flush">
                    @forelse ($meetings as $meeting)
                        <li class="list-group-item small">
                            <a href="{{ $meeting->meeting_link }}" target="_blank" class="text-decoration-none fw-semibold">{{ $meeting->title }}</a>
                            <div class="text-muted">{{ $meeting->meeting_date->format('M d') }} · {{ \Illuminate\Support\Carbon::parse($meeting->meeting_time)->format('g:i A') }}</div>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-camera-video" title="No meetings scheduled" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-box-arrow-in-up-right me-1"></i> Recent Implementation Requests</span>
                    <a href="{{ route('implementation-requests.index') }}" class="small">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentImplementationRequests as $req)
                        <li class="list-group-item small d-flex justify-content-between">
                            <span>{{ $req->lead?->company_name }} — {{ $req->title }}</span>
                            <x-status-badge :status="$req->status" />
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-box-arrow-in-up-right" title="No implementation requests yet" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-life-preserver me-1"></i> Recent Support Tickets</span>
                    <a href="{{ route('support-tickets.index') }}" class="small">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentSupportTickets as $ticket)
                        <li class="list-group-item small d-flex justify-content-between">
                            <span>{{ $ticket->subject }}</span>
                            <x-status-badge :status="$ticket->status" />
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-life-preserver" title="No support tickets yet" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-12">
            <x-activity-feed-widget />
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new Chart(document.getElementById('statusChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($statusDistribution->pluck('name')),
                        datasets: [{
                            data: @json($statusDistribution->pluck('leads_count')),
                            backgroundColor: @json($statusDistribution->pluck('color')),
                        }],
                    },
                    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } },
                });

                new Chart(document.getElementById('conversionChart'), {
                    type: 'bar',
                    data: {
                        labels: @json($monthlyConversion->pluck('month')),
                        datasets: [{
                            label: 'Deals Closed',
                            data: @json($monthlyConversion->pluck('total')),
                            backgroundColor: '#2456a6',
                        }],
                    },
                    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                });
            });
        </script>
    @endpush
@endsection
