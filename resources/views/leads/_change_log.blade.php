@forelse ($changeLog as $entry)
    <div class="border-bottom pb-2 mb-2 small">
        <span class="fw-semibold">{{ $entry->user?->name ?? 'Unknown' }}</span>
        <span class="text-muted">{{ $entry->created_at->format('M d, Y g:i A') }}</span>
        <ul class="mb-0 mt-1">
            @foreach ($entry->new_values as $field => $newValue)
                <li>
                    <strong>{{ \Illuminate\Support\Str::headline($field) }}:</strong>
                    {{ $entry->old_values[$field] ?? '—' }} &rarr; {{ $newValue ?? '—' }}
                </li>
            @endforeach
        </ul>
    </div>
@empty
    <x-empty-state icon="bi-clock-history" title="No changes logged yet" />
@endforelse
