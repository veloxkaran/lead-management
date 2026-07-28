<form method="POST" action="{{ route('leads.support-tickets.store', $lead) }}" enctype="multipart/form-data" class="row g-2 mb-3">
    @csrf
    <div class="col-md-3">
        <input type="text" name="subject" class="form-control form-control-sm" placeholder="Ticket subject" required>
    </div>
    <div class="col-md-2">
        <textarea name="details" rows="1" class="form-control form-control-sm" placeholder="Details (optional)" style="resize: none; overflow: hidden;" oninput="this.style.height='';this.style.height=this.scrollHeight+'px'"></textarea>
    </div>
    <div class="col-md-2">
        <select name="priority" class="form-select form-select-sm">
            @foreach ($priorities as $priority)
                <option value="{{ $priority->value }}" @selected($priority->value === 'medium')>{{ $priority->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="assigned_to" class="form-select form-select-sm">
            <option value="">Unassigned</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input type="file" name="attachments[]" multiple class="form-control form-control-sm" title="Attach documents">
    </div>
    <div class="col-md-1">
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Subject</th><th>Priority</th><th>Status</th><th>Documents</th><th>Assigned</th><th></th></tr></thead>
        <tbody>
            @forelse ($lead->supportTickets as $ticket)
                <tr>
                    <td class="small"><a href="{{ route('support-tickets.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                    <td><x-status-badge :status="$ticket->priority" /></td>
                    <td><x-status-badge :status="$ticket->status" /></td>
                    <td class="small">
                        @if ($ticket->attachments->isNotEmpty())
                            <a href="{{ route('support-tickets.show', $ticket) }}" class="text-decoration-none">
                                <i class="bi bi-paperclip"></i> {{ $ticket->attachments->count() }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small">{{ $ticket->assignee?->name ?? '—' }}</td>
                    <td>
                        @can('update', $ticket)
                            <a href="{{ route('support-tickets.edit', $ticket) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="bi-life-preserver" title="No support tickets yet" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
