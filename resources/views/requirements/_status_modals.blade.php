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
                    <div class="modal-body">
                        <p class="small text-muted">{{ $requirement->summary(120) }}</p>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($requirement->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Notes *</label>
                            <textarea name="note" rows="3" class="form-control" required placeholder="Explain why the status is changing"></textarea>
                            <div class="form-text">A note is required to change the status.</div>
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
