@php $releaseNote = $releaseNote ?? null; @endphp
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label small fw-semibold">Version *</label>
        <input type="text" name="version" value="{{ old('version', $releaseNote->version ?? '') }}" class="form-control" placeholder="v1.0.0" required>
    </div>
    <div class="col-md-9">
        <label class="form-label small fw-semibold">Title *</label>
        <input type="text" name="title" value="{{ old('title', $releaseNote->title ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Release Date *</label>
        <input type="date" name="release_date" value="{{ old('release_date', optional($releaseNote?->release_date)->format('Y-m-d')) }}" class="form-control" required>
    </div>
    <div class="col-md-8">
        <label class="form-label small fw-semibold">Google Drive Video Link</label>
        <input type="url" name="google_drive_video_link" value="{{ old('google_drive_video_link', $releaseNote->google_drive_video_link ?? '') }}" class="form-control" placeholder="https://drive.google.com/...">
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">Description *</label>
        <textarea name="description" rows="6" class="form-control" required>{{ old('description', $releaseNote->description ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">Attachments</label>
        <input type="file" name="attachments[]" class="form-control" multiple>
        <div class="form-text">Up to 20MB per file. You can attach multiple files.</div>
    </div>
    @if ($releaseNote && $releaseNote->attachments->isNotEmpty())
        <div class="col-12">
            <label class="form-label small fw-semibold">Existing Attachments</label>
            <ul class="list-group">
                @foreach ($releaseNote->attachments as $attachment)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-paperclip me-1"></i>{{ $attachment->original_name }}</span>
                        <a href="{{ $attachment->url() }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i></a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
