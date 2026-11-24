{{-- "Change status & add note" modal for each requirement in $requirements the viewer may change. --}}
@foreach ($requirements as $requirement)
    @can('changeStatus', $requirement)
        <div class="modal fade" id="statusModal-{{ $requirement->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('requirements.status.update', $requirement) }}" class="modal-content">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title">Change Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" x-data="{ status: @js($requirement->status->value) }">
                        <p class="small text-muted">{{ $requirement->summary(120) }}</p>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select" required x-model="status">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($requirement->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" x-text="status === 'cancelled' ? 'Cancellation reason *' : 'Notes *'">Notes *</label>
                            <textarea name="note" rows="3" maxlength="2000" class="form-control" required :placeholder="status === 'cancelled' ? 'Why is this requirement being cancelled?' : 'Explain why the status is changing'" placeholder="Explain why the status is changing"></textarea>
                            <div class="form-text" x-text="status === 'cancelled' ? 'Shown on the requirement as the reason it was cancelled.' : 'A note is required to change the status.'">A note is required to change the status.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endforeach
