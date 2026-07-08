@php $goal = $goal ?? null; @endphp
<div x-data="{ goalType: '{{ old('goal_type', $goal->goal_type?->value ?? 'individual') }}' }">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Title *</label>
            <input type="text" name="title" value="{{ old('title', $goal->title ?? '') }}" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Target *</label>
            <div class="input-group">
                <span class="input-group-text">{{ \App\Support\Currency::SYMBOL }}</span>
                <input type="number" step="0.01" min="0" name="target" value="{{ old('target', $goal->target ?? '') }}" class="form-control" required>
            </div>
        </div>
        @if ($goal)
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Achieved</label>
                <input type="text" value="{{ \App\Support\Currency::format($goal->achieved) }} ({{ $goal->achievementPercentage() }}%)" class="form-control" disabled>
                <div class="form-text">Auto-calculated from leads whose status is flagged as an achievement.</div>
            </div>
        @endif

        <div class="col-md-4">
            <label class="form-label small fw-semibold">Goal Type *</label>
            <select name="goal_type" class="form-select" x-model="goalType" required>
                @foreach (\App\Enums\GoalType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('goal_type', $goal->goal_type?->value ?? 'individual') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4" x-show="goalType === 'team'" x-cloak>
            <label class="form-label small fw-semibold">Team *</label>
            <select name="team_id" class="form-select" data-select2>
                <option value="">Select a team</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected(old('team_id', $goal->team_id ?? '') == $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4" x-show="goalType === 'individual'" x-cloak>
            <label class="form-label small fw-semibold">User *</label>
            <select name="user_id" class="form-select" data-select2>
                <option value="">Select a user</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(old('user_id', $goal->user_id ?? '') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

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
</div>
