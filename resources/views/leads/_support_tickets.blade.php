<form method="POST" action="{{ route('leads.support-tickets.store', $lead) }}" enctype="multipart/form-data" class="row g-2 mb-3">
    @csrf
    <div class="col-md-4">
        <input type="text" name="subject" class="form-control form-control-sm" placeholder="Ticket subject" required>
    </div>
    <div class="col-6 col-md-2">
        @include('support-tickets._type_select', ['selected' => null, 'size' => 'form-select-sm'])
    </div>
    <div class="col-6 col-md-2">
        <select name="priority" class="form-select form-select-sm" aria-label="Priority">
            @foreach ($priorities as $priority)
                <option value="{{ $priority->value }}" @selected($priority->value === 'medium')>{{ $priority->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <select name="assigned_to" class="form-select form-select-sm" aria-label="Assign to">
            <option value="">Unassigned</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <textarea name="details" rows="1" class="form-control form-control-sm" placeholder="Details (optional)" style="resize: none; overflow: hidden;" oninput="this.style.height='';this.style.height=this.scrollHeight+'px'"></textarea>
    </div>
    <div class="col-md-4">
        <input type="file" name="attachments[]" multiple class="form-control form-control-sm" title="Attach documents">
    </div>
    <div class="col-md-2">
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> Raise</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Subject</th><th>Type</th><th>Priority</th><th>Status</th><th>Documents</th><th>Assigned</th><th></th></tr></thead>
        <tbody>
            @forelse ($lead->supportTickets as $ticket)
                <tr>
                    <td class="small">
                        <a href="{{ route('support-tickets.show', $ticket) }}">{{ $ticket->subject }}</a>
                        @if ($ticket->is_client_submitted)
                            <span class="badge bg-info-subtle text-info-emphasis ms-1">Client</span>
                        @endif
                    </td>
                    <td>@if ($ticket->type)<x-status-badge :status="$ticket->type" />@else<span class="text-muted small">—</span>@endif</td>
                    <td><x-status-badge :status="$ticket->priority" /></td>
                    <td><x-status-badge :status="$ticket->status" /><x-cancellation-reason :record="$ticket" /></td>
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
                <tr><td colspan="7"><x-empty-state icon="bi-life-preserver" title="No support tickets yet" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
