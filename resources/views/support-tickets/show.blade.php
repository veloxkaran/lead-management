@extends('layouts.app')

@section('title', 'Support Ticket')

@section('content')
    <x-page-header title="{{ $supportTicket->subject }}" icon="bi-life-preserver" :subtitle="$supportTicket->lead?->company_name">
        <x-slot:actions>
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

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="small text-muted">Status</div>
                    <x-status-badge :status="$supportTicket->status" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Priority</div>
                    <x-status-badge :status="$supportTicket->priority" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Raised By</div>
                    <div class="small fw-semibold">{{ $supportTicket->raiser?->name ?? 'Unknown' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Assigned To</div>
                    <div class="small fw-semibold">{{ $supportTicket->assignee?->name ?? 'Unassigned' }}</div>
                </div>
                <div class="col-12">
                    <div class="small text-muted">Details</div>
                    <p class="mb-0">{{ $supportTicket->details ?: 'No details provided.' }}</p>
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

    @include('support-tickets._assignment_log')

    @include('support-tickets._comments')
@endsection
