@props([
    'name',
    'label' => null,
    'value' => '',
    'required' => false,
    'placeholder' => null,
    // Adds the image button (and paste/drop of images) — images arrive as base64 data URIs for the server to store.
    'images' => false,
    // Shows a live "x KB of y KB" counter (HTML + images) when set.
    'maxBytes' => null,
    // Already-stored images in $value: file name => bytes, so the counter can include them.
    'imageSizes' => [],
])

<div data-rich-text-editor @if($placeholder) data-rich-text-placeholder="{{ $placeholder }}" @endif
     @if($images) data-rich-text-images @endif
     @if($maxBytes) data-rich-text-max-bytes="{{ $maxBytes }}" data-rich-text-image-sizes="{{ json_encode((object) $imageSizes) }}" @endif>
    @if ($label)
        <label class="form-label small fw-semibold">{{ $label }}{{ $required ? ' *' : '' }}</label>
    @endif
    <div class="rich-text-editor-shell border rounded">
        <div data-rich-text-toolbar></div>
        <div data-rich-text-body style="min-height: 140px;"></div>
    </div>
    <textarea name="{{ $name }}" data-rich-text-input class="d-none">{{ $value }}</textarea>
    @if ($maxBytes)
        <div class="form-text" data-rich-text-size aria-live="polite"></div>
    @endif
    @error($name)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
