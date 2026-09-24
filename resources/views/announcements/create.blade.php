@extends('layouts.app')

@section('title', 'New Announcement')

@section('content')
    <x-page-header title="New Announcement" icon="bi-broadcast">
        <x-slot:actions>
            <a href="{{ route('announcements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Announcements
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('announcements.store') }}" enctype="multipart/form-data"
          x-data="announcementComposer({
              audience: @js(old('audience', \App\Enums\AnnouncementAudience::AllLeads->value)),
              counts: @js($audienceCounts),
              statusCounts: @js($statusCounts),
              selected: @js(array_map('strval', old('lead_status_ids', []))),
              template: @js(['subject' => $template['subject'], 'body' => $template['body']]),
              appName: @js($appName),
              companyName: @js($companyName),
              limits: @js([
                  'images' => \App\Http\Requests\Announcement\StoreAnnouncementRequest::MAX_IMAGES,
                  'imageBytes' => \App\Http\Requests\Announcement\StoreAnnouncementRequest::MAX_IMAGE_KB * 1024,
                  'gifBytes' => \App\Http\Requests\Announcement\StoreAnnouncementRequest::MAX_GIF_KB * 1024,
                  'documents' => \App\Http\Requests\Announcement\StoreAnnouncementRequest::MAX_DOCUMENTS,
                  'documentBytes' => \App\Http\Requests\Announcement\StoreAnnouncementRequest::MAX_DOCUMENT_KB * 1024,
              ]),
          })"
          @submit="submitting = true">
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Announcement Title *</label>
                            <input type="text" name="title" x-model="title" value="{{ old('title') }}" maxlength="200" class="form-control @error('title') is-invalid @enderror" required>
                            <div class="form-text">Used as the email subject.</div>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Content *</label>
                            <textarea name="content" x-model="content" rows="10" maxlength="10000" class="form-control @error('content') is-invalid @enderror" required>{{ old('content') }}</textarea>
                            <div class="form-text">Plain text; line breaks are kept. Wrapped in the <a href="{{ route('email-templates.index') }}">Announcement email template</a>.</div>
                            @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Images --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Images</label>
                            <input type="file" name="images[]" x-ref="images" @change="refresh('images')" accept="image/jpeg,image/png,image/gif" multiple
                                   class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror">
                            <div class="form-text">Up to 5 JPG, PNG or GIF images (8MB each; GIF 2MB). Large photos are resized automatically. Shown inline below the content.</div>
                            @error('images')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @foreach ($errors->get('images.*') as $messages)
                                <div class="invalid-feedback d-block">{{ $messages[0] }}</div>
                            @endforeach

                            <div class="d-flex flex-wrap gap-2 mt-2" x-show="files.images.length" x-cloak>
                                <template x-for="(file, index) in files.images" :key="file.url">
                                    <div class="position-relative border rounded" :class="file.error && 'border-danger'" style="width: 96px;">
                                        <a href="#" @click.prevent="openPreview(file)" :title="'Preview ' + file.name">
                                            <img :src="file.url" :alt="file.name" class="rounded-top d-block" style="width: 94px; height: 72px; object-fit: cover;">
                                        </a>
                                        <div class="px-1 text-truncate" style="font-size: 0.7rem;" x-text="file.name"></div>
                                        <div class="px-1 pb-1" style="font-size: 0.68rem;" :class="file.error ? 'text-danger' : 'text-muted'" x-text="file.error || file.size"></div>
                                        <button type="button" class="btn btn-sm btn-light border position-absolute top-0 end-0 m-1 py-0 px-1 lh-1" @click="remove('images', index)" title="Remove">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Documents --}}
                        <div>
                            <label class="form-label small fw-semibold">Documents</label>
                            <input type="file" name="documents[]" x-ref="documents" @change="refresh('documents')" multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt"
                                   class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror">
                            <div class="form-text">Up to 3 PDF, Word, Excel, PowerPoint, CSV or TXT files, 5MB each. Sent as email attachments. Images and documents together must stay under 7MB.</div>
                            @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @foreach ($errors->get('documents.*') as $messages)
                                <div class="invalid-feedback d-block">{{ $messages[0] }}</div>
                            @endforeach

                            <ul class="list-group mt-2" x-show="files.documents.length" x-cloak>
                                <template x-for="(file, index) in files.documents" :key="file.url">
                                    <li class="list-group-item d-flex align-items-center gap-2 py-2">
                                        <i class="bi fs-5" :class="docIcon(file.name)"></i>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="small text-truncate" x-text="file.name"></div>
                                            <div style="font-size: 0.72rem;" :class="file.error ? 'text-danger' : 'text-muted'" x-text="file.error || file.size"></div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="openPreview(file)" title="Preview"><i class="bi bi-eye"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="remove('documents', index)" title="Remove"><i class="bi bi-trash"></i></button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold">Recipients</div>
                    <div class="card-body">
                        @foreach ($audiences as $audience)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="audience" id="audience_{{ $audience->value }}" value="{{ $audience->value }}" x-model="audience">
                                <label class="form-check-label small" for="audience_{{ $audience->value }}">
                                    {{ $audience->label() }}
                                    @isset($audienceCounts[$audience->value])
                                        <span class="text-muted">({{ $audienceCounts[$audience->value] }})</span>
                                    @endisset
                                </label>
                            </div>
                        @endforeach
                        @error('audience')<div class="text-danger small">{{ $message }}</div>@enderror

                        <div class="mt-2" x-show="audience === 'lead_statuses'" x-cloak>
                            <label class="form-label small fw-semibold">Lead Statuses</label>
                            @foreach ($statuses as $status)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="lead_status_ids[]" id="status_{{ $status->id }}" value="{{ $status->id }}" x-model="selected">
                                    <label class="form-check-label small" for="status_{{ $status->id }}">
                                        {{ $status->name }} <span class="text-muted">({{ $statusCounts[$status->id] }})</span>
                                    </label>
                                </div>
                            @endforeach
                            @error('lead_status_ids')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <hr>
                        <div class="small">
                            <div class="fw-semibold">Will be sent to <span x-text="reach"></span> recipient(s)</div>
                            <div class="text-muted mt-1">
                                Only active leads with a valid email address; duplicate addresses get one copy.
                                Sent gradually, about {{ $perMinute }} per minute, to stay within the mail server's hourly limit.
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <button type="button" class="btn btn-primary w-100" :disabled="reach === 0 || hasFileErrors" @click="openEmailPreview()">
                            <i class="bi bi-eye"></i> Preview &amp; Send
                        </button>
                        <div class="small text-danger mt-1" x-show="hasFileErrors" x-cloak>Remove the files marked in red first.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Full email preview — the last step before sending. --}}
        <div class="modal fade" x-ref="emailModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div style="min-width: 0;">
                            <h6 class="modal-title mb-0">Email Preview</h6>
                            <div class="small text-muted text-truncate">Subject: <span class="text-body" x-text="rendered.subject"></span></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="background-color: #f4f5f7;">
                        <div class="mx-auto bg-white rounded overflow-hidden" style="max-width: 600px;">
                            <div class="px-4 py-3 text-white fw-semibold" style="background-color: #2456a6;" x-text="companyName"></div>
                            <div class="p-4 small" style="color: #1f2937; line-height: 1.6;">
                                <div style="white-space: pre-line;" x-text="rendered.body"></div>
                                <template x-for="file in files.images" :key="'p' + file.url">
                                    <img :src="file.url" :alt="file.name" class="d-block rounded mt-3" style="max-width: 100%; height: auto;">
                                </template>
                            </div>
                            <div class="px-4 py-2 border-top" x-show="files.documents.length">
                                <div class="small text-muted mb-1"><i class="bi bi-paperclip"></i> <span x-text="files.documents.length"></span> attachment(s)</div>
                                <template x-for="file in files.documents" :key="'a' + file.url">
                                    <span class="badge bg-light text-dark border me-1 mb-1"><i class="bi" :class="docIcon(file.name)"></i> <span x-text="file.name"></span></span>
                                </template>
                            </div>
                            <div class="px-4 py-2 border-top text-muted" style="font-size: 12px;">This is an automated message from <span x-text="companyName"></span>.</div>
                        </div>
                        <div class="small text-muted text-center mt-2">"Contact Name" and "Company" are replaced with each recipient's details.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-pencil"></i> Back to Edit</button>
                        <button type="submit" class="btn btn-primary" :disabled="submitting">
                            <span x-show="!submitting"><i class="bi bi-send"></i> Send to <span x-text="reach"></span> recipient(s)</span>
                            <span x-show="submitting" x-cloak><span class="spinner-border spinner-border-sm"></span> Sending…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Single-file preview (selected image or document). --}}
        <div class="modal fade" x-ref="fileModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title text-truncate" x-text="previewFile?.name"></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <template x-if="previewFile && previewFile.type.startsWith('image/')">
                            <img :src="previewFile.url" :alt="previewFile.name" class="img-fluid">
                        </template>
                        <template x-if="previewFile && (previewFile.type === 'application/pdf' || previewFile.type.startsWith('text/'))">
                            <iframe :src="previewFile.url" style="width: 100%; height: 70vh; border: 0;"></iframe>
                        </template>
                        <template x-if="previewFile && ! previewFile.type.startsWith('image/') && previewFile.type !== 'application/pdf' && ! previewFile.type.startsWith('text/')">
                            <div class="py-4">
                                <i class="bi fs-1 d-block mb-2" :class="docIcon(previewFile.name)"></i>
                                <p class="text-muted small mb-0">Browsers can't preview Word, Excel or PowerPoint files. It will be attached to the email as-is.</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        window.announcementComposer = function (config) {
            return {
                ...config,
                title: @js(old('title', '')),
                content: @js(old('content', '')),
                files: { images: [], documents: [] },
                previewFile: null,
                submitting: false,

                get reach() {
                    if (this.audience === 'lead_statuses') {
                        return this.selected.reduce((sum, id) => sum + (this.statusCounts[id] ?? 0), 0);
                    }
                    return this.counts[this.audience] ?? 0;
                },

                get hasFileErrors() {
                    return [...this.files.images, ...this.files.documents].some((file) => file.error);
                },

                get rendered() {
                    const values = {
                        title: this.title || '(no title)',
                        content: this.content || '(no content)',
                        contact_person: 'Contact Name',
                        company_name: 'Company',
                        app_name: this.appName,
                    };
                    const merge = (text) => text.replace(/\{\{\s*(\w+)\s*\}\}/g, (match, key) => values[key] ?? '');

                    return { subject: merge(this.template.subject), body: merge(this.template.body) };
                },

                refresh(kind) {
                    this.files[kind].forEach((file) => URL.revokeObjectURL(file.url));
                    this.files[kind] = Array.from(this.$refs[kind].files).map((file, index) => ({
                        name: file.name,
                        type: file.type || '',
                        url: URL.createObjectURL(file),
                        size: this.humanSize(file.size),
                        error: this.validate(kind, file, index),
                    }));
                },

                validate(kind, file, index) {
                    const limits = this.limits;
                    if (kind === 'images') {
                        if (index >= limits.images) return `Only ${limits.images} images allowed`;
                        if (! ['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) return 'Not JPG/PNG/GIF';
                        if (file.type === 'image/gif' && file.size > limits.gifBytes) return 'GIF over 2MB';
                        if (file.size > limits.imageBytes) return 'Over 8MB';
                    } else {
                        if (index >= limits.documents) return `Only ${limits.documents} documents allowed`;
                        if (! /\.(pdf|docx?|xlsx?|pptx?|csv|txt)$/i.test(file.name)) return 'Unsupported file type';
                        if (file.size > limits.documentBytes) return 'Over 5MB';
                    }
                    return null;
                },

                // FileList is read-only — rebuild it without the removed file.
                remove(kind, index) {
                    const transfer = new DataTransfer();
                    Array.from(this.$refs[kind].files).forEach((file, i) => i !== index && transfer.items.add(file));
                    this.$refs[kind].files = transfer.files;
                    this.refresh(kind);
                },

                openPreview(file) {
                    this.previewFile = file;
                    bootstrap.Modal.getOrCreateInstance(this.$refs.fileModal).show();
                },

                openEmailPreview() {
                    if (! this.$el.closest('form').reportValidity()) return;
                    bootstrap.Modal.getOrCreateInstance(this.$refs.emailModal).show();
                },

                docIcon(name) {
                    const ext = (name.split('.').pop() || '').toLowerCase();
                    return {
                        pdf: 'bi-file-earmark-pdf text-danger',
                        doc: 'bi-file-earmark-word text-primary', docx: 'bi-file-earmark-word text-primary',
                        xls: 'bi-file-earmark-excel text-success', xlsx: 'bi-file-earmark-excel text-success', csv: 'bi-file-earmark-excel text-success',
                        ppt: 'bi-file-earmark-ppt text-warning', pptx: 'bi-file-earmark-ppt text-warning',
                    }[ext] || 'bi-file-earmark-text';
                },

                humanSize(bytes) {
                    return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
                },
            };
        };
    </script>
@endpush
