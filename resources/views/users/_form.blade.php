@php $user = $user ?? null; @endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Name *</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">Email *</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $user->phone ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Department</label>
        <input type="text" name="department" value="{{ old('department', $user->department ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Designation</label>
        <input type="text" name="designation" value="{{ old('designation', $user->designation ?? '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Role *</label>
        <select name="role" class="form-select" required>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user->role->value ?? 'user') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Status *</label>
        <select name="status" class="form-select" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $user->status->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Team</label>
        <select name="team_id" class="form-select" data-select2>
            <option value="">No team</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected(old('team_id', $user->team_id ?? '') == $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
    </div>
    @if (!$user)
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Password *</label>
            <input type="password" name="password" class="form-control" required>
        </div>
    @endif
</div>
