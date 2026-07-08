@php $leadStatus = $leadStatus ?? null; @endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Name *</label>
        <input type="text" name="name" value="{{ old('name', $leadStatus->name ?? '') }}" class="form-control" required>
        @if ($leadStatus)
            <div class="form-text">Slug: <code>{{ $leadStatus->slug }}</code></div>
        @else
            <div class="form-text">The slug will be generated automatically from the name.</div>
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Color *</label>
        <div class="input-group">
            <input type="color" name="color" value="{{ old('color', $leadStatus->color ?? '#6c757d') }}" class="form-control form-control-color" title="Choose status color">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" id="is_default" value="1" class="form-check-input" @checked(old('is_default', $leadStatus->is_default ?? false))>
            <label class="form-check-label" for="is_default">Default status</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check">
            <input type="hidden" name="is_closed_won" value="0">
            <input type="checkbox" name="is_closed_won" id="is_closed_won" value="1" class="form-check-input" @checked(old('is_closed_won', $leadStatus->is_closed_won ?? false))>
            <label class="form-check-label" for="is_closed_won">Closed &mdash; Won</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check">
            <input type="hidden" name="is_closed_lost" value="0">
            <input type="checkbox" name="is_closed_lost" id="is_closed_lost" value="1" class="form-check-input" @checked(old('is_closed_lost', $leadStatus->is_closed_lost ?? false))>
            <label class="form-check-label" for="is_closed_lost">Closed &mdash; Lost</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check">
            <input type="hidden" name="is_achievement" value="0">
            <input type="checkbox" name="is_achievement" id="is_achievement" value="1" class="form-check-input" @checked(old('is_achievement', $leadStatus->is_achievement ?? false))>
            <label class="form-check-label" for="is_achievement">Counts as Achievement</label>
        </div>
        <div class="form-text">While a lead sits in this status, its Achieved Cost counts toward Goal progress.</div>
    </div>
</div>
