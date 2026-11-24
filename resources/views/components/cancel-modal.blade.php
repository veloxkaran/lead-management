@props(['id', 'action', 'noun', 'mentions' => null])

{{-- "Cancel with a reason" dialog for a requirement/support ticket. Opens on its own after a missing reason. --}}
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title">
    <div class="modal-dialog">
        <form method="POST" action="{{ $action }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Title"><i class="bi bi-x-octagon"></i> Cancel {{ $noun }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    It's marked <strong>Cancelled</strong> and no longer counts as open work. The reason is shown on the {{ $noun }}
                    and posted as a comment. It can be reopened later if needed.
                </p>
                <label class="form-label small fw-semibold" for="{{ $id }}Reason">Why is it being cancelled? *</label>
                <textarea name="cancellation_reason" id="{{ $id }}Reason" rows="3" maxlength="2000"
                          class="form-control @error('cancellation_reason') is-invalid @enderror"
                          placeholder="e.g. The client no longer needs this… type @ to mention a teammate"
                          @if ($mentions) data-mentions="{{ $mentions }}" @endif required>{{ old('cancellation_reason') }}</textarea>
                @error('cancellation_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep it</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-octagon"></i> Cancel {{ $noun }}</button>
            </div>
        </form>
    </div>
</div>

@if ($errors->has('cancellation_reason'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById(@js($id))).show());
        </script>
    @endpush
@endif
