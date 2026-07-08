@extends('layouts.app')

@section('title', 'Follow-ups')

@section('content')
    <x-page-header title="Follow-ups" icon="bi-bell" subtitle="Track reminders and scheduled follow-ups across leads.">
        <x-slot:actions>
            @can('create', App\Models\FollowUp::class)
                <a href="{{ route('follow-ups.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Schedule Follow-up
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('follow-ups.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Lead</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Reminder</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($followUps as $followUp)
                        @php
                            $isOverdue = $followUp->status === \App\Enums\FollowUpStatus::Pending
                                && \Illuminate\Support\Carbon::parse($followUp->follow_up_date->toDateString().' '.$followUp->follow_up_time)->isPast();
                        @endphp
                        <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                            <td>
                                @if ($followUp->lead)
                                    <a href="{{ route('leads.show', $followUp->lead) }}" class="text-decoration-none fw-semibold">{{ $followUp->lead->company_name }}</a>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="small">{{ $followUp->follow_up_date->format('M d, Y') }}</td>
                            <td class="small">{{ \Illuminate\Support\Carbon::parse($followUp->follow_up_time)->format('g:i A') }}</td>
                            <td class="small">{{ $followUp->reminder_type->label() }} &middot; {{ $followUp->reminder_minutes_before }}m before</td>
                            <td><x-status-badge :status="$followUp->status" /></td>
                            <td class="text-end">
                                @can('update', $followUp)
                                    <a href="{{ route('follow-ups.edit', $followUp) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $followUp)
                                    <form method="POST" action="{{ route('follow-ups.destroy', $followUp) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this follow-up?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-bell" title="No follow-ups found" description="Try adjusting your filters or schedule a new follow-up." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($followUps->hasPages())
            <div class="card-footer bg-white">
                {{ $followUps->links() }}
            </div>
        @endif
    </div>
@endsection
