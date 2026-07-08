<form method="POST" action="{{ route('leads.follow-ups.store', $lead) }}" class="row g-2 mb-3">
    @csrf
    <div class="col-md-3">
        <input type="date" name="follow_up_date" class="form-control form-control-sm" required>
    </div>
    <div class="col-md-2">
        <input type="time" name="follow_up_time" class="form-control form-control-sm" required>
    </div>
    <div class="col-md-3">
        <select name="reminder_type" class="form-select form-select-sm">
            @foreach ($reminderTypes as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <input type="number" min="5" name="reminder_minutes_before" class="form-control form-control-sm" placeholder="Remind (mins before)" value="30">
    </div>
    <div class="col-md-1">
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Date</th><th>Time</th><th>Reminder</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse ($lead->followUps as $followUp)
                <tr>
                    <td>{{ $followUp->follow_up_date->format('M d, Y') }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($followUp->follow_up_time)->format('g:i A') }}</td>
                    <td class="small">{{ $followUp->reminder_type->label() }} · {{ $followUp->reminder_minutes_before }}m before</td>
                    <td><x-status-badge :status="$followUp->status" /></td>
                    <td>
                        <a href="{{ route('follow-ups.edit', $followUp) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state icon="bi-bell" title="No follow-ups scheduled" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
