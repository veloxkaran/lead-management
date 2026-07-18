@php $training = $lead->latestTraining; @endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-mortarboard"></i> Training Status</h6>
        @if ($training)
            <div class="d-flex justify-content-between align-items-center mb-2">
                <x-status-badge :status="$training->status" />
                <span class="small text-muted">Updated {{ $training->updated_at->diffForHumans() }}</span>
            </div>
            <div class="progress mb-2" style="height: 6px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $training->completion_percentage }}%" aria-valuenow="{{ $training->completion_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <dl class="row small mb-0">
                <dt class="col-6 text-muted">Training Date</dt><dd class="col-6">{{ $training->training_date?->format('M d, Y') ?? '—' }}</dd>
                <dt class="col-6 text-muted">Trainer</dt><dd class="col-6">{{ $training->trainer_name ?? '—' }}</dd>
                <dt class="col-6 text-muted">Attendees</dt><dd class="col-6">{{ $training->attendees_count ?? '—' }}</dd>
                <dt class="col-6 text-muted">Completion</dt><dd class="col-6">{{ $training->completion_percentage }}%</dd>
            </dl>
            @if ($training->feedback)
                <hr>
                <h6 class="fw-semibold small">Feedback</h6>
                <p class="small text-muted mb-0">{{ $training->feedback }}</p>
            @endif
        @else
            <x-empty-state icon="bi-mortarboard" title="No training scheduled yet" />
        @endif
    </div>
    <div class="card-footer bg-white">
        <a href="{{ route('leads.trainings.index', $lead) }}" class="btn btn-sm btn-outline-primary w-100">Open Complete Training History</a>
    </div>
</div>
