@php
    $document = $document ?? null;
    $assignmentField = $assignmentField ?? null;
    $currentVersion = $document?->currentVersion ?? $document?->versions->first();
@endphp
<div class="row g-3">
    <div class="col-md-{{ $assignmentField ? 8 : 12 }}">
        <label class="form-label small fw-semibold">Title *</label>
        <input type="text" name="title" value="{{ old('title', $document->title ?? '') }}" class="form-control" required>
    </div>
    @if ($assignmentField)
        <div class="col-md-4">
            <label class="form-label small fw-semibold">{{ $assignmentLabel }} *</label>
            <select name="{{ $assignmentField }}" class="form-select" data-select2 required>
                <option value="">Select {{ strtolower($assignmentLabel) }}...</option>
                @foreach ($assignmentOptions as $option)
                    <option value="{{ $option->id }}" @selected(old($assignmentField, $document?->{$assignmentField} ?? '') == $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-md-6 form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked(old('is_active', $document->is_active ?? true))>
        <label class="form-check-label small">Active (visible to assigned employees)</label>
    </div>
    <div class="col-md-6 form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="allow_skip" value="1" @checked(old('allow_skip', $document->allow_skip ?? false))>
        <label class="form-check-label small">Allow employees to skip this step</label>
    </div>

    @unless ($document)
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Version</label>
            <input type="text" name="version" value="{{ old('version', '1.0') }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Effective Date *</label>
            <input type="date" name="effective_date" value="{{ old('effective_date', now()->toDateString()) }}" class="form-control" required>
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">Content *</label>
            <input id="policy-document-content" type="hidden" name="content" value="{{ old('content') }}">
            <trix-editor input="policy-document-content" class="form-control"></trix-editor>
        </div>
    @endunless
</div>

@if ($document && $currentVersion)
    <hr class="my-4">
    <h6 class="fw-semibold mb-2">Current Version</h6>
    <p class="small text-muted mb-3">
        v{{ $currentVersion->version }} · effective {{ $currentVersion->effective_date->format('M d, Y') }} ·
        published {{ $currentVersion->published_at->format('M d, Y') }} by {{ $currentVersion->creator?->name }}
    </p>
    <div class="border rounded p-3 mb-3 small">{!! $currentVersion->content !!}</div>
@endif
