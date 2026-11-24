@extends('layouts.app')

@section('title', 'Edit Support Ticket')

@section('content')
    <x-page-header title="Support Ticket" icon="bi-life-preserver" :subtitle="$supportTicket->lead?->company_name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('support-tickets.update', $supportTicket) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="row g-3" x-data="{ status: @js(old('status', $supportTicket->status->value)) }">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Subject</label>
                        <input type="text" name="subject" class="form-control" value="{{ old('subject', $supportTicket->subject) }}" required @readonly(! $supportTicket->detailsEditable())>
                        @error('subject')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Type</label>
                        @include('support-tickets._type_select', ['selected' => old('type', $supportTicket->type?->value)])
                        @error('type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        @if ($supportTicket->detailsEditable())
                            <x-rich-text-editor name="details" label="Details" :value="old('details', App\Support\RichText::toHtml($supportTicket->details))" placeholder="Describe the issue..." />
                        @else
                            {{-- Locked: shown read-only and not submitted, so it can't trip the lock check. --}}
                            <label class="form-label small fw-semibold">Details</label>
                            <div class="rich-content border rounded p-2 bg-body-tertiary">
                                @if (filled($supportTicket->details))
                                    {!! $supportTicket->detailsHtml() !!}
                                @else
                                    <span class="text-muted small">No details provided.</span>
                                @endif
                            </div>
                            @error('details')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @endif
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
                        <select name="status" class="form-select" x-model="status">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $supportTicket->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Assigned To</label>
                        <select name="assigned_to" class="form-select" data-select2-field>
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('assigned_to', $supportTicket->assigned_to) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12" x-show="status === 'cancelled'" x-cloak>
                        <label class="form-label small fw-semibold" for="cancellationReason">Why is it being cancelled? *</label>
                        <textarea name="cancellation_reason" id="cancellationReason" rows="2" maxlength="2000" class="form-control" :required="status === 'cancelled'" placeholder="Shown on the ticket as the cancellation reason">{{ old('cancellation_reason', $supportTicket->cancellation_reason) }}</textarea>
                        @error('cancellation_reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Add Documents</label>
                        <input type="file" name="attachments[]" multiple class="form-control">
                        @error('attachments.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Ticket</button>
                    <a href="{{ route('support-tickets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-paperclip"></i> Documents ({{ $supportTicket->attachments->count() }})</div>
        <div class="card-body">
            @forelse ($supportTicket->attachments as $attachment)
                <a href="{{ route('support-ticket-attachments.download', $attachment) }}" class="badge bg-light text-dark border text-decoration-none me-1 mb-1">
                    <i class="bi bi-paperclip"></i> {{ $attachment->original_name }}
                </a>
            @empty
                <p class="text-muted small mb-0">No documents attached yet.</p>
            @endforelse
        </div>
    </div>
@endsection
