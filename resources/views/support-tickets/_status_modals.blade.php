{{-- "Change status & add note" popup for each ticket in $tickets the viewer may change — mirrors requirements/_status_modals. --}}
@foreach ($tickets as $ticket)
    @can('changeStatus', $ticket)
        @php($formId = 'ticket_status_'.$ticket->id)
        <div class="modal fade" id="ticketStatusModal-{{ $ticket->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('support-tickets.status.update', $ticket) }}" class="modal-content">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_form" value="{{ $formId }}">
                    <div class="modal-header">
                        <h5 class="modal-title">Change Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" x-data="{ status: @js(old('_form') === $formId ? old('status') : $ticket->status->value) }">
                        <p class="small text-muted">{{ $ticket->subject }}</p>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select" required x-model="status">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($ticket->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" x-text="status === 'cancelled' ? 'Cancellation reason *' : 'Notes *'">Notes *</label>
                            <textarea name="note" rows="3" maxlength="2000" class="form-control" required
                                      data-mentions="{{ route('mentions.users', ['support_ticket', $ticket->id]) }}"
                                      :placeholder="status === 'cancelled' ? 'Why is this ticket being cancelled?' : 'Explain why the status is changing… type @ to mention a teammate'"
                                      placeholder="Explain why the status is changing">{{ old('_form') === $formId ? old('note') : '' }}</textarea>
                            <div class="form-text" x-text="status === 'cancelled' ? 'Shown on the ticket as the reason it was cancelled.' : 'A note is required to change the status. It is added to the ticket\'s comments.'">A note is required to change the status.</div>
                            @if (old('_form') === $formId)
                                @error('note')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($errors->any() && old('_form') === $formId)
            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById(@js('ticketStatusModal-'.$ticket->id))).show());
                </script>
            @endpush
        @endif
    @endcan
@endforeach
