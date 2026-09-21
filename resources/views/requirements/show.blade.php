@extends('layouts.app')

@section('title', 'Requirement')

@section('content')
    <x-page-header title="Requirement" icon="bi-list-check" :subtitle="$requirement->lead?->company_name">
        <x-slot:actions>
            @can('update', $requirement)
                <a href="{{ route('requirements.edit', $requirement) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endcan
            <a href="{{ route('requirements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                @if ($requirement->title)
                    <div class="col-12">
                        <div class="small text-muted">Title</div>
                        <p class="mb-0 fw-semibold">{{ $requirement->title }}</p>
                    </div>
                @endif
                <div class="col-12">
                    <div class="small text-muted">Requirement</div>
                    <div class="requirement-rich-content">{!! $requirement->requirementHtml() !!}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Priority</div>
                    <x-status-badge :status="$requirement->priority" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Status</div>
                    <x-status-badge :status="$requirement->status" />
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Due Date</div>
                    <div class="small fw-semibold">
                        {{ $requirement->due_date?->format('M d, Y') ?? '—' }}
                        @if ($requirement->due_date && $requirement->due_date->isPast() && $requirement->status->value !== 'completed')
                            <span class="badge bg-danger-subtle text-danger-emphasis">Overdue</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Assigned To</div>
                    <div class="small fw-semibold">{{ $requirement->assignee?->name ?? 'Unassigned' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Client Acknowledged</div>
                    <div class="small fw-semibold">
                        @if ($requirement->isAcknowledgedByClient())
                            <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> {{ $requirement->client_acknowledged_at->format('M d, Y g:i A') }}</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Not yet</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Sprint</div>
                    <div class="small fw-semibold">{{ $requirement->sprint ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Created By</div>
                    <div class="small fw-semibold">{{ $requirement->creator?->name ?? 'Unknown' }} on {{ $requirement->created_at->format('M d, Y g:i A') }}</div>
                </div>
                <div class="col-12" x-data="attachmentPreview()">
                    <div class="small text-muted">Attachments</div>
                    @forelse ($requirement->attachments as $attachment)
                        <span class="badge bg-light text-dark border me-1 mb-1">
                            <i class="bi bi-paperclip"></i>
                            <a href="#" class="text-decoration-none text-dark" @click.prevent="open(@js(route('requirement-attachments.preview', $attachment)), @js($attachment->original_name), @js($attachment->mime_type))">
                                {{ $attachment->original_name }}
                            </a>
                            <a href="{{ route('requirement-attachments.download', $attachment) }}" class="text-muted ms-1" title="Download {{ $attachment->original_name }}">
                                <i class="bi bi-download"></i>
                            </a>
                        </span>
                    @empty
                        <span class="small text-muted">No attachments.</span>
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
            </div>
        </div>
    </div>

    @include('requirements._comments')
    @include('requirements._change_log')
@endsection
