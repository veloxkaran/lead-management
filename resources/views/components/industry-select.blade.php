{{--
    Industry dropdown for lead forms — options come from the admin-managed
    list (Administration → Industries). Leads store the industry by name,
    so a lead filed under a name that's since left the list shows a hint
    to pick a current one instead of silently losing its value.
--}}
@props([
    'industries',
    'selected' => null,
    'label' => 'Industry',
    'compact' => false,
])

@php
    $current = old('industry', $selected);
    $isStale = filled($selected) && ! $industries->contains('name', $selected);
@endphp

@if ($label)
    <label class="form-label small fw-semibold">{{ $label }} *</label>
@endif
<select name="industry" {{ $attributes->class(['form-select', 'form-select-sm' => $compact]) }} @unless($compact) data-select2-field @endunless required>
    <option value="">Select industry</option>
    @foreach ($industries as $industry)
        <option value="{{ $industry->name }}" @selected($current === $industry->name)>{{ $industry->name }}</option>
    @endforeach
</select>
@if ($industries->isEmpty())
    <div class="form-text text-warning">
        No industries have been set up yet.
        @if (auth()->user()?->isSuperAdmin())
            <a href="{{ route('industries.index') }}">Add industries</a>.
        @else
            Ask an administrator to add them.
        @endif
    </div>
@elseif ($isStale)
    <div class="form-text text-warning">Previously “{{ $selected }}”, which is no longer in the list — choose a current industry.</div>
@endif
@error('industry')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
