@forelse ($activity as $entry)
    <div class="border-bottom pb-2 mb-2">
        <div class="d-flex justify-content-between">
            <strong class="small">{{ $entry->user?->name ?? 'System' }}</strong>
            <span class="text-muted small">{{ $entry->created_at->diffForHumans() }}</span>
        </div>
        <p class="small mb-1">{{ $entry->description }}</p>
        @if ($entry->old_values || $entry->new_values)
            <ul class="small text-muted mb-0">
                @foreach (array_keys($entry->new_values ?? []) as $field)
                    <li>
                        <strong>{{ $field }}</strong>:
                        {{ $entry->old_values[$field] ?? '—' }} &rarr; {{ $entry->new_values[$field] ?? '—' }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@empty
    <x-empty-state icon="bi-clock-history" title="No activity recorded yet" />
@endforelse
