@extends('layouts.app')

@section('title', 'Edit Support Ticket')

@section('content')
    <x-page-header title="Support Ticket" icon="bi-life-preserver" :subtitle="$supportTicket->lead?->company_name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('support-tickets.update', $supportTicket) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Subject</label>
                        <input type="text" name="subject" class="form-control" value="{{ old('subject', $supportTicket->subject) }}" required @readonly(! $supportTicket->detailsEditable())>
                        @error('subject')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Details</label>
                        <textarea name="details" rows="4" class="form-control" @readonly(! $supportTicket->detailsEditable())>{{ old('details', $supportTicket->details) }}</textarea>
                        @error('details')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    @unless ($supportTicket->detailsEditable())
                        <div class="col-12">
                            <div class="small text-muted fst-italic">Subject and details are locked — they can only be edited within 12 hours of the ticket being raised.</div>
                        </div>
                    @endunless
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}" @selected(old('priority', $supportTicket->priority->value) === $priority->value)>{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $supportTicket->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Assigned To</label>
                        <select name="assigned_to" class="form-select" data-select2>
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('assigned_to', $supportTicket->assigned_to) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Ticket</button>
                    <a href="{{ route('support-tickets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
