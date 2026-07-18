@php $goal = $goal ?? null; @endphp
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label small fw-semibold">Title *</label>
        <input type="text" name="title" value="{{ old('title', $goal->title ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Category *</label>
        <select name="category" class="form-select" required>
            @foreach (\App\Enums\GoalCategory::cases() as $category)
                <option value="{{ $category->value }}" @selected(old('category', $goal->category?->value ?? '') === $category->value)>
                    {{ $category->label() }}{{ $category->isDealDriven() ? ' — auto-tracked' : '' }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Auto-tracked categories update automatically when a deal is closed. Others start at 0 until tracked manually.</div>
    </div>

    <div class="col-12">
        <label class="form-label small fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $goal->description ?? '') }}</textarea>
    </div>

    <div class="col-md-4">
        <label class="form-label small fw-semibold">Target *</label>
        <div class="input-group">
            <span class="input-group-text">{{ \App\Support\Currency::SYMBOL }}</span>
            <input type="number" step="0.01" min="0" name="target" value="{{ old('target', $goal->target ?? '') }}" class="form-control" required>
        </div>
    </div>
    @if ($goal)
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Achieved</label>
            <input type="text" value="{{ \App\Support\Currency::format($goal->achieved) }} ({{ $goal->achievementPercentage() }}%)" class="form-control" disabled>
            <div class="form-text">Auto-calculated from closed deals for auto-tracked categories.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Status</label>
            <div><x-status-badge :status="$goal->status()" /></div>
        </div>
    @endif

    <div class="col-md-3">
        <label class="form-label small fw-semibold">Start Date *</label>
        <input type="date" name="start_date" value="{{ old('start_date', optional($goal->start_date ?? null)->format('Y-m-d')) }}" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold">End Date *</label>
        <input type="date" name="end_date" value="{{ old('end_date', optional($goal->end_date ?? null)->format('Y-m-d')) }}" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold">BS Year (optional)</label>
        <input type="number" name="bs_year" value="{{ old('bs_year', $goal->bs_year ?? '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold">BS Month (optional)</label>
        <input type="number" min="1" max="12" name="bs_month" value="{{ old('bs_month', $goal->bs_month ?? '') }}" class="form-control">
    </div>
</div>
