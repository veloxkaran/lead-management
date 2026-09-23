@props([
    'name',
    'label' => null,
    'value' => '',
    'required' => false,
    'placeholder' => null,
])

<div data-ckeditor-field @if($placeholder) data-ckeditor-placeholder="{{ $placeholder }}" @endif>
    @if ($label)
        <label class="form-label small fw-semibold">{{ $label }}{{ $required ? ' *' : '' }}</label>
    @endif
    <textarea name="{{ $name }}" data-ckeditor>{{ $value }}</textarea>
    @error($name)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
