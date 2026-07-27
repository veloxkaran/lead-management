<form method="POST" action="{{ route('leads.requirements.store', $lead) }}" class="row g-2 mb-3">
    @csrf
    <div class="col-md-5">
        <textarea name="requirement" rows="1" class="form-control form-control-sm" placeholder="Describe the requirement" required style="resize: none; overflow: hidden;" oninput="this.style.height='';this.style.height=this.scrollHeight+'px'"></textarea>
    </div>
    <div class="col-md-2">
        <select name="priority" class="form-select form-select-sm">
            @foreach ($priorities as $priority)
                <option value="{{ $priority->value }}" @selected($priority->value === 'medium')>{{ $priority->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="due_date" class="form-control form-control-sm" title="Due date">
    </div>
    <div class="col-md-2">
        <select name="assigned_to" class="form-select form-select-sm">
            <option value="">Unassigned</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-1">
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Requirement</th><th>Priority</th><th>Status</th><th>Due Date</th><th>Client Acknowledged</th><th>Assigned</th><th></th></tr></thead>
        <tbody>
            @forelse ($lead->requirements as $requirement)
                <tr>
                    <td class="small">
                        <a href="{{ route('requirements.show', $requirement) }}" class="text-decoration-none">{{ $requirement->requirement }}</a>
                        @if ($requirement->comments->count())
                            <span class="text-muted"><i class="bi bi-chat-left-text"></i> {{ $requirement->comments->count() }}</span>
                        @endif
                    </td>
                    <td><x-status-badge :status="$requirement->priority" /></td>
                    <td><x-status-badge :status="$requirement->status" /></td>
                    <td class="small">
                        {{ $requirement->due_date?->format('M d, Y') ?? '—' }}
                        @if ($requirement->due_date && $requirement->due_date->isPast() && $requirement->status->value !== 'completed')
                            <span class="badge bg-danger-subtle text-danger-emphasis">Overdue</span>
                        @endif
                    </td>
                    <td class="small">
                        @if ($requirement->isAcknowledgedByClient())
                            <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle"></i> {{ $requirement->client_acknowledged_at->format('M d, Y g:i A') }}</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Not yet</span>
                        @endif
                    </td>
                    <td class="small">{{ $requirement->assignee?->name ?? '—' }}</td>
                    <td>
                        <a href="{{ route('requirements.show', $requirement) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        @can('update', $requirement)
                            <a href="{{ route('requirements.edit', $requirement) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="bi-list-check" title="No requirements yet" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
