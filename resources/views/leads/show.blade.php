@extends('layouts.app')

@section('title', $lead->company_name)

@section('content')
    @php
        $initials = collect(preg_split('/\s+/', trim($lead->company_name)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
        $openRequirements = $lead->requirements->filter(fn ($r) => $r->status !== App\Enums\RequirementStatus::Completed)->count();
        $openTickets = $lead->supportTickets->whereNull('resolved_at')->count();
        $nextFollowUp = $lead->followUps
            ->filter(fn ($f) => $f->status === App\Enums\FollowUpStatus::Pending && $f->follow_up_date->gte(today()))
            ->sortBy(fn ($f) => $f->follow_up_date->format('Y-m-d').' '.$f->follow_up_time)
            ->first();
        $websiteUrl = $lead->website ? (Str::startsWith($lead->website, ['http://', 'https://']) ? $lead->website : 'https://'.$lead->website) : null;
        $tabs = [
            'activities' => ['Activities', 'bi-clock-history', $lead->activities->count()],
            'notes' => ['Notes', 'bi-sticky', $lead->notes->count()],
            'followups' => ['Follow Ups', 'bi-bell', $lead->followUps->count()],
            'requirements' => ['Requirements', 'bi-list-check', $lead->requirements->count()],
            'support-tickets' => ['Support Tickets', 'bi-life-preserver', $lead->supportTickets->count()],
            'history' => ['Status History', 'bi-signpost-split', $lead->statusHistories->count()],
            'change-log' => ['Change Log', 'bi-journal-text', $changeLog->count()],
        ];
    @endphp

    {{-- Header: who this lead is, where it stands, and the main actions. --}}
    <div class="card mb-3">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-start gap-3">
                <span class="lead-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h1 class="h4 fw-bold mb-0">{{ $lead->company_name }}</h1>
                        @if ($lead->status)
                            <span class="badge" style="background-color: {{ $lead->status->color }}">{{ $lead->status->name }}</span>
                        @endif
                        @if ($lead->dealClosure)
                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><i class="bi bi-trophy-fill"></i> Deal Closed</span>
                        @endif
                        @if ($lead->isAchieved())
                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><i class="bi bi-check-circle"></i> Achieved</span>
                        @endif
                        @if ($lead->isArchived())
                            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle"><i class="bi bi-archive"></i> Archived</span>
                        @endif
                    </div>
                    <div class="text-muted small mt-1">
                        {{ collect([$lead->industry, $lead->contact_person])->filter()->implode(' · ') ?: 'No industry or contact set' }}
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if ($lead->email)
                            <a href="mailto:{{ $lead->email }}" class="lead-chip"><i class="bi bi-envelope"></i> {{ $lead->email }}</a>
                        @endif
                        @if ($lead->phone)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $lead->phone) }}" class="lead-chip"><i class="bi bi-telephone"></i> {{ $lead->phone }}</a>
                        @endif
                        @if ($websiteUrl)
                            <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer" class="lead-chip"><i class="bi bi-globe2"></i> {{ $lead->website }}</a>
                        @endif
                        <span class="lead-chip lead-chip-static" title="Lead owner">
                            @if ($lead->assignedUser)
                                <x-user-avatar :user="$lead->assignedUser" :size="18" /> {{ $lead->assignedUser->name }}
                            @else
                                <i class="bi bi-person"></i> Unassigned
                            @endif
                        </span>
                        <span class="lead-chip lead-chip-static" title="Time in current status">
                            <i class="bi bi-hourglass-split"></i> In status for {{ $lead->currentStatusAge() }}
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if (! $lead->dealClosure && ! $lead->isArchived())
                        @can('close', $lead)
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#closeDealModal">
                                <i class="bi bi-trophy"></i> Close Deal
                            </button>
                        @endcan
                    @endif
                    @can('update', $lead)
                        <a href="{{ route('leads.edit', $lead) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                    @endcan
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-label="More actions">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('leads.walkthrough', $lead) }}"><i class="bi bi-stars me-2"></i>Walkthrough</a></li>
                            @can('exportPdf', App\Models\Lead::class)
                                <li><a class="dropdown-item" href="{{ route('leads.export-pdf', $lead) }}"><i class="bi bi-file-earmark-pdf me-2"></i>Download PDF</a></li>
                            @endcan
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="lead-kpis">
            <div class="lead-kpi">
                <div class="lead-kpi-label">Opportunity</div>
                <div class="lead-kpi-value">{{ $lead->opportunity_cost !== null ? \App\Support\Currency::format($lead->opportunity_cost) : '—' }}</div>
            </div>
            <div class="lead-kpi">
                <div class="lead-kpi-label">Achieved</div>
                <div class="lead-kpi-value"><x-currency :amount="$lead->achieved_cost" /></div>
            </div>
            <div class="lead-kpi">
                <div class="lead-kpi-label">Open Requirements</div>
                <div class="lead-kpi-value">{{ $openRequirements }} <span class="lead-kpi-sub">/ {{ $lead->requirements->count() }}</span></div>
            </div>
            <div class="lead-kpi">
                <div class="lead-kpi-label">Open Tickets</div>
                <div class="lead-kpi-value">{{ $openTickets }} <span class="lead-kpi-sub">/ {{ $lead->supportTickets->count() }}</span></div>
            </div>
            <div class="lead-kpi">
                <div class="lead-kpi-label">Next Follow Up</div>
                <div class="lead-kpi-value">
                    @if ($nextFollowUp)
                        {{ $nextFollowUp->follow_up_date->format('M d') }}
                        <span class="lead-kpi-sub">{{ $nextFollowUp->follow_up_date->isToday() ? 'today' : $nextFollowUp->follow_up_date->diffForHumans(['parts' => 1]) }}</span>
                    @else
                        <span class="text-muted">None</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="lead-section-title"><i class="bi bi-signpost-split"></i> Status</h6>
                    @can('changeStatus', $lead)
                        <form method="POST" action="{{ route('leads.status.update', $lead) }}" class="d-flex gap-2">
                            @csrf
                            <div class="flex-grow-1">
                                <select name="lead_status_id" class="form-select form-select-sm" data-select2-field aria-label="Lead status">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->id }}" @selected($lead->lead_status_id === $status->id)>{{ $status->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary">Update</button>
                        </form>
                    @else
                        <span class="badge" style="background-color: {{ $lead->status?->color }}">{{ $lead->status?->name }}</span>
                    @endcan
                    <div class="text-muted small mt-2">
                        <i class="bi bi-clock-history"></i> In this status for {{ $lead->currentStatusAge() }}
                    </div>
                </div>
            </div>

            @if ($lead->dealClosure)
                <div class="card mb-3 lead-deal-card">
                    <div class="card-body">
                        <h6 class="lead-section-title text-success"><i class="bi bi-trophy-fill"></i> Deal Closed</h6>
                        <div class="fs-5 fw-bold"><x-currency :amount="$lead->dealClosure->deal_value" /></div>
                        <p class="small text-muted mb-1">Closed by {{ $lead->dealClosure->closedBy?->name }} on {{ $lead->dealClosure->closed_date->format('M d, Y') }}</p>
                        @if ($lead->dealClosure->closing_comment)
                            <p class="small mb-0">{{ $lead->dealClosure->closing_comment }}</p>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="lead-section-title"><i class="bi bi-building"></i> Contact &amp; Company</h6>
                    <ul class="lead-detail-list">
                        <li><i class="bi bi-person"></i><span class="lead-detail-label">Contact</span><span class="lead-detail-value">{{ $lead->contact_person ?: '—' }}</span></li>
                        <li><i class="bi bi-envelope"></i><span class="lead-detail-label">Email</span><span class="lead-detail-value">@if ($lead->email)<a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>@else — @endif</span></li>
                        <li><i class="bi bi-telephone"></i><span class="lead-detail-label">Phone</span><span class="lead-detail-value">@if ($lead->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $lead->phone) }}">{{ $lead->phone }}</a>@else — @endif</span></li>
                        <li><i class="bi bi-globe2"></i><span class="lead-detail-label">Website</span><span class="lead-detail-value">@if ($websiteUrl)<a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer">{{ $lead->website }}</a>@else — @endif</span></li>
                        <li><i class="bi bi-geo-alt"></i><span class="lead-detail-label">Address</span><span class="lead-detail-value">{{ $lead->address ?: '—' }}</span></li>
                        <li><i class="bi bi-people"></i><span class="lead-detail-label">Employees</span><span class="lead-detail-value">{{ $lead->number_of_employees ?: '—' }}</span></li>
                        <li><i class="bi bi-funnel"></i><span class="lead-detail-label">Source</span><span class="lead-detail-value">{{ $lead->source ?: '—' }}</span></li>
                        <li><i class="bi bi-person-badge"></i><span class="lead-detail-label">Assigned To</span><span class="lead-detail-value">{{ $lead->assignedUser?->name ?? '—' }}</span></li>
                        <li><i class="bi bi-person-plus"></i><span class="lead-detail-label">Created By</span><span class="lead-detail-value">{{ $lead->creator?->name ?? '—' }} <span class="text-muted">· {{ $lead->created_at->format('M d, Y') }}</span></span></li>
                    </ul>
                </div>
            </div>

            @if ($lead->business_details || $lead->about_client_business)
                <div class="card mb-3">
                    <div class="card-body">
                        @if ($lead->business_details)
                            <h6 class="lead-section-title"><i class="bi bi-briefcase"></i> Business Details</h6>
                            <p class="small mb-0" style="white-space: pre-line;">{{ $lead->business_details }}</p>
                        @endif
                        @if ($lead->about_client_business)
                            <h6 @class(['lead-section-title', 'mt-3' => $lead->business_details])><i class="bi bi-info-circle"></i> About Client</h6>
                            <p class="small mb-0" style="white-space: pre-line;">{{ $lead->about_client_business }}</p>
                        @endif
                    </div>
                </div>
            @endif

            @can('viewProgressStatus', $lead)
                @include('leads._training_status')
            @endcan

            @can('manageSupportAccess', $lead)
                @include('leads._support_access')
            @endcan
        </div>

        <div class="col-lg-8">
            <div class="card" x-data="leadTabs({{ $lead->id }})">
                <div class="card-header p-0">
                    <ul class="nav nav-underline lead-tabs flex-nowrap px-3" id="leadTabs" role="tablist">
                        @foreach ($tabs as $id => [$label, $icon, $count])
                            <li class="nav-item" role="presentation">
                                <button @class(['nav-link', 'active' => $loop->first]) data-bs-toggle="tab" data-bs-target="#{{ $id }}" type="button" role="tab" aria-controls="{{ $id }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                    <i class="bi {{ $icon }}"></i> {{ $label }}
                                    @if ($count)
                                        <span class="lead-tab-count">{{ $count }}</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="tab-content card-body">
                    <div class="tab-pane fade show active" id="activities" role="tabpanel">
                        @include('leads._activities')
                    </div>
                    <div class="tab-pane fade" id="notes" role="tabpanel">
                        @include('leads._notes')
                    </div>
                    <div class="tab-pane fade" id="followups" role="tabpanel">
                        @include('leads._followups')
                    </div>
                    <div class="tab-pane fade" id="requirements" role="tabpanel">
                        @include('leads._requirements')
                    </div>
                    <div class="tab-pane fade" id="support-tickets" role="tabpanel">
                        @include('leads._support_tickets')
                    </div>
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        @include('leads._status_history')
                    </div>
                    <div class="tab-pane fade" id="change-log" role="tabpanel">
                        @include('leads._change_log')
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Reopen the tab the viewer was last on for this lead (e.g. after adding a note the page reloads onto Notes).
            window.leadTabs = function (leadId) {
                const key = `lead-tab-${leadId}`;

                return {
                    init() {
                        let saved = null;
                        try {
                            saved = sessionStorage.getItem(key);
                        } catch (e) {}

                        const button = saved && this.$el.querySelector(`[data-bs-target="#${saved}"]`);
                        if (button) {
                            bootstrap.Tab.getOrCreateInstance(button).show();
                        }

                        this.$el.addEventListener('shown.bs.tab', (event) => {
                            try {
                                sessionStorage.setItem(key, event.target.dataset.bsTarget.slice(1));
                            } catch (e) {}
                        });
                    },
                };
            };
        </script>
    @endpush

    @can('close', $lead)
        <div class="modal fade" id="closeDealModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('leads.close', $lead) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Close Deal — {{ $lead->company_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Closed Date</label>
                            <input type="date" name="closed_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Deal Value</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ \App\Support\Currency::SYMBOL }}</span>
                                <input type="number" step="0.01" min="0" name="deal_value" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Closing Comment</label>
                            <textarea name="closing_comment" rows="3" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Close Deal</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection
