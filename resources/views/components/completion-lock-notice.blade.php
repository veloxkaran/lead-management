@props(['record', 'noun'])

{{-- Shows the 4-hour post-completion edit window (see LocksAfterCompletion). --}}
@if ($locksAt = $record->locksAt())
    @if ($record->isLocked())
        <div class="alert alert-secondary d-flex align-items-center gap-2 py-2 small">
            <i class="bi bi-lock-fill"></i>
            <div>
                This {{ $noun }} was completed on {{ $record->locksAt()->copy()->subHours($record::EDIT_WINDOW_HOURS)->format('M d, Y g:i A') }} and is now locked.
                @if (auth()->user()->isSuperAdmin())
                    As Super Admin you can still edit or reopen it.
                @else
                    Ask a Super Admin if it needs to be reopened.
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-info d-flex align-items-center gap-2 py-2 small">
            <i class="bi bi-hourglass-split"></i>
            <div>
                Completed — it can still be edited until <strong>{{ $locksAt->format('M d, Y g:i A') }}</strong> ({{ $locksAt->diffForHumans() }}), then it locks.
            </div>
        </div>
    @endif
@endif
