@extends('layouts.app')

@section('title', 'Email Log')

@section('content')
    <x-page-header title="Email Log" icon="bi-envelope-paper" subtitle="Every client notification email the system has sent." />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
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
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('email-logs.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Related To</th>
                        <th>Status</th>
                        <th>Sent</th>
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
                                    Requirement — {{ \Illuminate\Support\Str::limit($log->related->requirement, 40) }}
                                @elseif ($log->related instanceof \App\Models\SupportTicket)
                                    Support Ticket — {{ $log->related->subject }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td><x-status-badge :status="$log->status" /></td>
                            <td class="small text-muted">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                            <td class="text-end">
                                <a href="{{ route('email-logs.show', $log) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-envelope-paper" title="No emails sent yet" description="Notification emails appear here once a requirement or support ticket is created or changes status." />
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
