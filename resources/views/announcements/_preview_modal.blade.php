{{-- Driven by the shared attachmentPreview() Alpine helper (resources/js/attachment-preview.js). --}}
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
                <template x-if="isPdf || (mimeType || '').startsWith('text/')">
                    <iframe :src="url" style="width: 100%; height: 70vh; border: 0;"></iframe>
                </template>
                <template x-if="!isImage && !isPdf && !(mimeType || '').startsWith('text/')">
                    <div class="py-4">
                        <p class="text-muted small mb-2">Browsers can't preview this file type.</p>
                        <a :href="url.replace('/preview', '/download')" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Download</a>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
