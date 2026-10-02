@extends('layouts.app')

@section('title', 'Email Log')

@section('content')
    <x-page-header title="Email Log" icon="bi-envelope-paper" subtitle="Every client notification email the system has queued or sent." />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end" x-data="{ period: '{{ $filters['period'] ?? '' }}' }">
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Recipient or subject" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2-field>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Date</label>
                    <select name="period" class="form-select form-select-sm" x-model="period">
                        <option value="">Any time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3" x-show="period === 'custom'" x-cloak>
                    <label class="form-label small">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3" x-show="period === 'custom'" x-cloak>
                    <label class="form-label small">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('email-logs.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2 py-2">
            <nav class="nav nav-pills small" aria-label="By status">
                @foreach ([null, ...$statuses] as $status)
                    @php
                        $value = $status?->value;
                        $isActive = ($filters['status'] ?? null) === $value;
                        $count = $value ? ($statusCounts[$value] ?? 0) : array_sum($statusCounts);
                    @endphp
                    <a href="{{ route('email-logs.index', array_filter([...$filters, 'status' => $value])) }}"
                       @class(['nav-link py-1 px-2 d-flex align-items-center gap-1', 'active' => $isActive, 'text-body' => ! $isActive])>
                        {{ $status?->label() ?? 'All' }}
                        <span @class(['badge rounded-pill', 'bg-white text-primary' => $isActive, 'bg-secondary-subtle text-secondary-emphasis' => ! $isActive])>{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </nav>
            <span class="ms-auto small text-muted" title="Plain email has no delivery receipt — an email counts as delivered once the recipient opens it (with images on)."><i class="bi bi-info-circle"></i> Delivered = opened by the recipient</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Related To</th>
                        <th>Status</th>
                        <th style="min-width: 240px;">Remarks</th>
                        <th>Queued</th>
                        <th>Sent / Opened</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="small">{{ $log->to_email }}</td>
                            <td class="small">{{ $log->subject }}</td>
                            <td class="small text-muted">
                                @if ($log->related instanceof \App\Models\Requirement)
                                    Requirement — {{ $log->related->summary(40) }}
                                @elseif ($log->related instanceof \App\Models\SupportTicket)
                                    Support Ticket — {{ $log->related->subject }}
                                @elseif ($log->related instanceof \App\Models\Announcement)
                                    Announcement — {{ $log->related->title }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td><x-status-badge :status="$log->status" /></td>
                            <td class="small">
                                <div @class(['text-danger' => $log->status === App\Enums\EmailLogStatus::Failed, 'text-warning-emphasis' => $log->status === App\Enums\EmailLogStatus::Pending, 'text-muted' => in_array($log->status, [App\Enums\EmailLogStatus::Sent, App\Enums\EmailLogStatus::Delivered], true)])>{{ $log->remarks() }}</div>
                                @if ($log->error)
                                    <div class="text-muted text-break font-monospace" style="font-size: .75rem;" title="{{ $log->error }}">{{ \Illuminate\Support\Str::limit($log->error, 140) }}</div>
                                @endif
                            </td>
                            <td class="small text-muted text-nowrap">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                            <td class="small text-muted text-nowrap">
                                {{ $log->sent_at?->format('M d, Y g:i A') ?? '—' }}
                                @if ($log->delivered_at)
                                    <div class="text-success"><i class="bi bi-envelope-open"></i> {{ $log->delivered_at->format('M d, g:i A') }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('email-logs.show', $log) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="bi-envelope-paper" title="No emails found" description="Notification emails appear here once a requirement or support ticket is created or changes status." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer bg-white">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
