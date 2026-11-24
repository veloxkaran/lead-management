@extends('layouts.app')

@section('title', 'Support Ticket')

@section('content')
    <x-page-header title="{{ $supportTicket->subject }}" icon="bi-life-preserver" :subtitle="$supportTicket->lead?->company_name">
        <x-slot:meta><x-lead-status :lead="$supportTicket->lead" /></x-slot:meta>
        <x-slot:actions>
            @can('reopen', $supportTicket)
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reopenTicketModal">
                    <i class="bi bi-arrow-counterclockwise"></i> Reopen
                </button>
            @endcan
            @can('cancel', $supportTicket)
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelTicketModal">
                    <i class="bi bi-x-octagon"></i> Cancel Ticket
                </button>
            @endcan
            @can('update', $supportTicket)
                <a href="{{ route('support-tickets.edit', $supportTicket) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endcan
            <a href="{{ route('support-tickets.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-cancellation-notice :record="$supportTicket" noun="support ticket" />

    <x-completion-lock-notice :record="$supportTicket" noun="support ticket" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="small text-muted">Status</div>
                    <x-status-badge :status="$supportTicket->status" />
                    <div class="small text-muted mt-1">
                        @if ($lastStatusChange = $supportTicket->statusLogs->first())
                            Changed {{ $lastStatusChange->created_at->format('M d, Y g:i A') }}
                        @else
                            Unchanged since raised
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Priority</div>
                    <x-status-badge :status="$supportTicket->priority" />
                    <div class="small text-muted mt-2">Type</div>
                    @if ($supportTicket->type)
                        <x-status-badge :status="$supportTicket->type" />
                    @else
                        <span class="small text-muted">Not set</span>
                    @endif
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Raised By</div>
                    <div class="small fw-semibold">{{ $supportTicket->raiserDisplayName() }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Assigned To</div>
                    <div class="small fw-semibold">{{ $supportTicket->assignee?->name ?? 'Unassigned' }}</div>
                    @can('assignToSelf', $supportTicket)
                        <form method="POST" action="{{ route('support-tickets.assign-to-me', $supportTicket) }}" class="mt-1">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm py-0"><i class="bi bi-person-check"></i> Assign to me</button>
                        </form>
                    @endcan
                </div>
                <div class="col-12">
                    <div class="small text-muted">Details</div>
                    @if (filled($supportTicket->details))
                        {{-- Same rich-text rendering as a requirement's description. --}}
                        <div class="rich-content">{!! $supportTicket->detailsHtml() !!}</div>
                    @else
                        <p class="mb-0 text-muted small">No details provided.</p>
                    @endif
                </div>
                <div class="col-12" x-data="attachmentPreview()">
                    <div class="small text-muted">Documents</div>
                    @forelse ($supportTicket->attachments as $attachment)
                        <span class="badge bg-light text-dark border me-1 mb-1">
                            <i class="bi bi-paperclip"></i>
                            <a href="#" class="text-decoration-none text-dark" @click.prevent="open(@js(route('support-ticket-attachments.preview', $attachment)), @js($attachment->original_name), @js($attachment->mime_type))">
                                {{ $attachment->original_name }}
                            </a>
                            <a href="{{ route('support-ticket-attachments.download', $attachment) }}" class="text-muted ms-1" title="Download {{ $attachment->original_name }}">
                                <i class="bi bi-download"></i>
                            </a>
                        </span>
                    @empty
                        <span class="small text-muted">No documents attached.</span>
                    @endforelse

                    <div class="modal fade" x-ref="previewModal" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title text-truncate" x-text="name"></h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <template x-if="isImage">
                                        <img :src="url" :alt="name" class="img-fluid">
                                    </template>
                                    <template x-if="isPdf">
                                        <iframe :src="url" style="width: 100%; height: 70vh; border: 0;"></iframe>
                                    </template>
                                    <template x-if="!isImage && !isPdf">
                                        <div class="py-4">
                                            <p class="text-muted small mb-2">Preview isn't available for this file type.</p>
                                            <a :href="url" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Open in new tab</a>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="small text-muted">
                        Raised on {{ $supportTicket->created_at->format('M d, Y g:i A') }}
                        @unless ($supportTicket->detailsEditable())
                            &middot; <span class="fst-italic">Details are now locked (12-hour edit window has passed).</span>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('support-tickets._status_log')

    @include('support-tickets._assignment_log')

    @include('support-tickets._comments')

    @can('cancel', $supportTicket)
        <x-cancel-modal id="cancelTicketModal" noun="ticket"
                        :action="route('support-tickets.cancel', $supportTicket)"
                        :mentions="route('mentions.users', ['support_ticket', $supportTicket->id])" />
    @endcan

    @can('reopen', $supportTicket)
        {{-- Reopen with a reason (kept as a comment). Opens on its own when coming from the list's reopen icon (?reopen=1) or after a missing reason. --}}
        <div class="modal fade" id="reopenTicketModal" tabindex="-1" aria-labelledby="reopenTicketTitle">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('support-tickets.reopen', $supportTicket) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="reopenTicketTitle"><i class="bi bi-arrow-counterclockwise"></i> Reopen ticket</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            It goes back to <strong>{{ $supportTicket->assigned_to ? 'In Progress' : 'Pending' }}</strong>{{ $supportTicket->assignee ? ' with '.$supportTicket->assignee->name : '' }},
                            and the client gets the usual status update email.
                        </p>
                        <label class="form-label small fw-semibold" for="reopenReason">Why is it being reopened?</label>
                        <textarea name="reason" id="reopenReason" rows="3" class="form-control @error('reason') is-invalid @enderror" placeholder="e.g. The client says the issue is back… type @ to mention a teammate" data-mentions="{{ route('mentions.users', ['support_ticket', $supportTicket->id]) }}" required>{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-counterclockwise"></i> Reopen</button>
                    </div>
                </form>
            </div>
        </div>

        @if (request()->boolean('reopen') || $errors->has('reason'))
            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('reopenTicketModal')).show());
                </script>
            @endpush
        @endif
    @endcan

@endsection
