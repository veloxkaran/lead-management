<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i> Publish New Version</div>
    <div class="card-body">
        <p class="small text-muted">
            Publishing a new version does not change the current one — everyone assigned
            will be asked to read and re-acknowledge the new content next time they log in.
        </p>
        <form method="POST" action="{{ route("{$routeName}.versions.store", $document) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Version *</label>
                    <input type="text" name="version" class="form-control" required
                        value="{{ old('version') }}" placeholder="e.g. 1.1">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Effective Date *</label>
                    <input type="date" name="effective_date" value="{{ old('effective_date', now()->toDateString()) }}" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Content *</label>
                    <input id="new-version-content" type="hidden" name="content" value="{{ old('content') }}">
                    <trix-editor input="new-version-content" class="form-control"></trix-editor>
                </div>
            </div>
            <button type="submit" class="btn btn-outline-primary mt-3"><i class="bi bi-upload"></i> Publish New Version</button>
        </form>
    </div>
</div>
