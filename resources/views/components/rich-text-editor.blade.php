@props([
    'name',
    'label' => null,
    'value' => '',
    'required' => false,
    'placeholder' => null,
])

<div data-rich-text-editor @if($placeholder) data-rich-text-placeholder="{{ $placeholder }}" @endif>
    @if ($label)
        <label class="form-label small fw-semibold">{{ $label }}{{ $required ? ' *' : '' }}</label>
    @endif
    <div class="rich-text-editor-shell border rounded">
        <div data-rich-text-toolbar></div>
        <div data-rich-text-body style="min-height: 140px;"></div>
    </div>
    <textarea name="{{ $name }}" data-rich-text-input class="d-none">{{ $value }}</textarea>
    @error($name)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
