<form method="POST" action="{{ route('leads.activities.store', $lead) }}" class="row g-2 mb-3">
    @csrf
    <div class="col-md-3">
        <select name="activity_type" class="form-select form-select-sm" required>
            @foreach ($activityTypes as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="activity_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
    </div>
    <div class="col-md-2">
        <input type="time" name="activity_time" class="form-control form-control-sm" value="{{ now()->format('H:i') }}" required>
    </div>
    <div class="col-md-4">
        <input type="text" name="description" class="form-control form-control-sm" placeholder="Describe the activity" required>
    </div>
    <div class="col-md-1">
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
    </div>
</form>

<div class="timeline">
    @forelse ($lead->activities as $activity)
        <div class="timeline-item">
            <div class="d-flex justify-content-between">
                <span class="fw-semibold small"><i class="bi {{ $activity->activity_type->icon() }} me-1"></i>{{ $activity->activity_type->label() }}</span>
                <span class="text-muted small">{{ $activity->activity_date->format('M d, Y') }} {{ \Illuminate\Support\Carbon::parse($activity->activity_time)->format('g:i A') }}</span>
            </div>
            <p class="small mb-0">{{ $activity->description }}</p>
            <span class="text-muted" style="font-size: 0.72rem;">by {{ $activity->creator?->name }}</span>
        </div>
    @empty
        <x-empty-state icon="bi-clock-history" title="No activities logged yet" />
    @endforelse
</div>
