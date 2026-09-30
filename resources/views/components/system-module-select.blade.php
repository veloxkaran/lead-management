{{--
    System module dropdown for requirement forms — options come from the
    admin-managed list (Administration → System Modules).
--}}
@props([
    'modules',
    'selected' => null,
    'label' => 'Module',
    'compact' => false,
])

@php $current = old('system_module_id', $selected); @endphp

@if ($label)
    <label class="form-label small fw-semibold">{{ $label }} *</label>
@endif
<select name="system_module_id" {{ $attributes->class(['form-select', 'form-select-sm' => $compact]) }} @unless($compact) data-select2-field @endunless required @if($compact) title="Module" @endif>
    <option value="">{{ $compact ? 'Module' : 'Select module' }}</option>
    @foreach ($modules as $module)
        <option value="{{ $module->id }}" @selected((string) $current === (string) $module->id)>{{ $module->name }}</option>
    @endforeach
</select>
@if ($modules->isEmpty())
    <div class="form-text text-warning">
        No system modules have been set up yet.
        @if (auth()->user()?->isSuperAdmin())
            <a href="{{ route('system-modules.index') }}">Add modules</a>.
        @else
            Ask an administrator to add them.
        @endif
    </div>
@endif
@error('system_module_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
