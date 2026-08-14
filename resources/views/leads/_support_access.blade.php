<x-settings-card title="Client Support Access" icon="bi-key">
    @if (session('new_support_pin'))
        <div class="alert alert-warning py-2 small">
            <i class="bi bi-exclamation-triangle"></i>
            New PIN: <strong class="fs-6">{{ session('new_support_pin') }}</strong>
            — share this with the client now. It will not be shown again.
        </div>
    @endif

    @if ($lead->hasSupportAccess())
        <dl class="row small mb-3">
            <dt class="col-5 text-muted">Support ID</dt>
            <dd class="col-7"><code>{{ $lead->support_id }}</code></dd>
            <dt class="col-5 text-muted">PIN last set</dt>
            <dd class="col-7">{{ $lead->support_pin_generated_at?->diffForHumans() }}
                @if ($lead->supportPinGeneratedBy)
                    by {{ $lead->supportPinGeneratedBy->name }}
                @endif
            </dd>
        </dl>
        <p class="text-muted small">
            Share the Support ID + PIN with this lead's client contact so they can raise support
            tickets directly at <a href="{{ route('client-support.show') }}" target="_blank">{{ route('client-support.show') }}</a>
            without a staff login.
        </p>
    @else
        <p class="text-muted small">No Support ID has been issued for this lead yet.</p>
    @endif

    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('leads.support-access.generate', $lead) }}"
            onsubmit="return confirm('{{ $lead->hasSupportAccess() ? 'This issues a brand new PIN — the old one will stop working immediately. Continue?' : 'Generate a Support ID and PIN for this lead?' }}');">
            @csrf
            <button class="btn btn-sm btn-primary">
                <i class="bi bi-arrow-repeat"></i> {{ $lead->hasSupportAccess() ? 'Regenerate PIN' : 'Generate Support ID & PIN' }}
            </button>
        </form>
        @if ($lead->hasSupportAccess())
            <form method="POST" action="{{ route('leads.support-access.revoke', $lead) }}"
                onsubmit="return confirm('Revoke this lead\'s Support ID and PIN? The client will no longer be able to submit tickets until a new one is issued.');">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Revoke</button>
            </form>
        @endif
    </div>
</x-settings-card>
