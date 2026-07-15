@props(['name', 'label', 'checked' => false])

<div {{ $attributes->merge(['class' => 'form-check form-switch']) }}>
    <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1" @checked($checked)>
    <label class="form-check-label small">{{ $label }}</label>
</div>
