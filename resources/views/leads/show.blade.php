@extends('layouts.app')

@section('title', $lead->company_name)

@section('content')
    <x-page-header :title="$lead->company_name" icon="bi-building" :subtitle="$lead->industry">
        <x-slot:actions>
            <a href="{{ route('leads.walkthrough', $lead) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-stars"></i> Walkthrough</a>
            @can('update', $lead)
                <a href="{{ route('leads.edit', $lead) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @if (!$lead->dealClosure && !$lead->isArchived())
                @can('close', $lead)
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#closeDealModal">
                        <i class="bi bi-trophy"></i> Close Deal
                    </button>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Lead Details</h6>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Contact</dt><dd class="col-7">{{ $lead->contact_person }}</dd>
                        <dt class="col-5 text-muted">Email</dt><dd class="col-7">{{ $lead->email ?: '—' }}</dd>
                        <dt class="col-5 text-muted">Phone</dt><dd class="col-7">{{ $lead->phone ?: '—' }}</dd>
                        <dt class="col-5 text-muted">WhatsApp</dt>
                        <dd class="col-7">
                            @if ($lead->whatsapp_number)
                                <i class="bi bi-whatsapp text-success"></i> {{ $lead->whatsapp_number }}
                                @can('chatWhatsapp', $lead)
                                    <a href="{{ route('whatsapp.show', $lead) }}" class="small ms-1">Chat</a>
                                @endcan
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-5 text-muted">Website</dt><dd class="col-7">{{ $lead->website ?: '—' }}</dd>
                        <dt class="col-5 text-muted">Address</dt><dd class="col-7">{{ $lead->address ?: '—' }}</dd>
                        <dt class="col-5 text-muted">Employees</dt><dd class="col-7">{{ $lead->number_of_employees ?: '—' }}</dd>
                        <dt class="col-5 text-muted">Source</dt><dd class="col-7">{{ $lead->source ?: '—' }}</dd>
                        <dt class="col-5 text-muted">Opportunity Cost</dt><dd class="col-7">{{ $lead->opportunity_cost !== null ? \App\Support\Currency::format($lead->opportunity_cost) : '—' }}</dd>
                        <dt class="col-5 text-muted">Achieved Cost</dt>
                        <dd class="col-7">
                            <x-currency :amount="$lead->achieved_cost" />
                            @if ($lead->isAchieved())
                                <span class="badge bg-success-subtle text-success-emphasis ms-1"><i class="bi bi-check-circle"></i> Achieved</span>
                            @endif
                        </dd>
                        <dt class="col-5 text-muted">Assigned To</dt><dd class="col-7">{{ $lead->assignedUser?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Created By</dt><dd class="col-7">{{ $lead->creator?->name }}</dd>
                    </dl>
                    @if ($lead->business_details)
                        <hr>
                        <h6 class="fw-semibold small">Business Details</h6>
                        <p class="small text-muted mb-0">{{ $lead->business_details }}</p>
                    @endif
                    @if ($lead->about_client_business)
                        <hr>
                        <h6 class="fw-semibold small">About Client</h6>
                        <p class="small text-muted mb-0">{{ $lead->about_client_business }}</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">Status</h6>
                    @can('changeStatus', $lead)
                        <form method="POST" action="{{ route('leads.status.update', $lead) }}" class="d-flex gap-2">
                            @csrf
                            <select name="lead_status_id" class="form-select form-select-sm" data-select2>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}" @selected($lead->lead_status_id === $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </select>
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
                <div class="card border-0 shadow-sm mb-3 border-start border-success border-3">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-2 text-success"><i class="bi bi-trophy-fill"></i> Deal Closed</h6>
                        <p class="small mb-1">Value: <strong><x-currency :amount="$lead->dealClosure->deal_value" /></strong></p>
                        <p class="small mb-1">Closed by {{ $lead->dealClosure->closedBy?->name }} on {{ $lead->dealClosure->closed_date->format('M d, Y') }}</p>
                        @if ($lead->dealClosure->closing_comment)
                            <p class="small text-muted mb-0">{{ $lead->dealClosure->closing_comment }}</p>
                        @endif
                    </div>
                </div>
            @endif

            @can('viewProgressStatus', $lead)
                @include('leads._implementation_status')
                @include('leads._training_status')
                @include('leads._subscription_status')
            @endcan
        </div>

        <div class="col-lg-8">
            <ul class="nav nav-tabs" id="leadTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#activities" type="button">Activities</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#notes" type="button">Notes</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#followups" type="button">Follow Ups</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#requirements" type="button">Requirements</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#support-tickets" type="button">Support Tickets</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tasks" type="button">Tasks</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#history" type="button">Status History</button></li>
            </ul>
            <div class="tab-content bg-white border border-top-0 rounded-bottom p-3 shadow-sm">
                <div class="tab-pane fade show active" id="activities">
                    @include('leads._activities')
                </div>
                <div class="tab-pane fade" id="notes">
                    @include('leads._notes')
                </div>
                <div class="tab-pane fade" id="followups">
                    @include('leads._followups')
                </div>
                <div class="tab-pane fade" id="requirements">
                    @include('leads._requirements')
                </div>
                <div class="tab-pane fade" id="support-tickets">
                    @include('leads._support_tickets')
                </div>
                <div class="tab-pane fade" id="tasks">
                    @include('leads._tasks')
                </div>
                <div class="tab-pane fade" id="history">
                    @include('leads._status_history')
                </div>
            </div>
        </div>
    </div>

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
