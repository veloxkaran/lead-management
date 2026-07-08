@php $meeting = $meeting ?? null; @endphp
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label small fw-semibold">Title *</label>
        <input type="text" name="title" value="{{ old('title', $meeting->title ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Team</label>
        <select name="team_id" class="form-select" data-select2>
            <option value="">Personal (no team)</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected(old('team_id', $meeting->team_id ?? '') == $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Date *</label>
        <input type="date" name="meeting_date" value="{{ old('meeting_date', optional($meeting?->meeting_date)->format('Y-m-d')) }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Time *</label>
        <input type="time" name="meeting_time" value="{{ old('meeting_time', $meeting ? \Illuminate\Support\Carbon::parse($meeting->meeting_time)->format('H:i') : '') }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Meeting Link *</label>
        <input type="url" name="meeting_link" value="{{ old('meeting_link', $meeting->meeting_link ?? '') }}" class="form-control" placeholder="https://meet.google.com/..." required>
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">Participants</label>
        <textarea name="participants" rows="4" class="form-control" placeholder="One name or email per line">{{ old('participants', $meeting ? implode("\n", $meeting->participants ?? []) : '') }}</textarea>
        <div class="form-text">Enter one participant (name or email) per line.</div>
    </div>
</div>
