@php $item = $item ?? null; @endphp
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label small fw-semibold">Title *</label>
        <input type="text" name="title" value="{{ old('title', $item->title ?? '') }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Category *</label>
        <select name="category_id" class="form-select" data-select2 required>
            <option value="">Select category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $item->category_id ?? '') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">Description</label>
        <textarea name="description" rows="3" class="form-control">{{ old('description', $item->description ?? '') }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">Type *</label>
        <select name="type" id="kb-type" class="form-select" required>
            @foreach (\App\Enums\KnowledgeBaseType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('type', $item->type->value ?? '') == $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-8" id="kb-file-field">
        <label class="form-label small fw-semibold">File{{ !$item ? ' *' : '' }}</label>
        <input type="file" name="file" class="form-control">
        @if ($item && $item->original_name)
            <div class="form-text">Current file: {{ $item->original_name }}. Upload a new file to replace it.</div>
        @endif
    </div>
    <div class="col-md-8 d-none" id="kb-link-field">
        <label class="form-label small fw-semibold">Link URL *</label>
        <input type="url" name="link_url" value="{{ old('link_url', $item->link_url ?? '') }}" class="form-control" placeholder="https://...">
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">Tags</label>
        <input type="text" name="tags" value="{{ old('tags', $item?->tags->pluck('name')->implode(', ')) }}" class="form-control" placeholder="onboarding, sales, template">
        <div class="form-text">Comma-separated list of tags.</div>
    </div>
</div>

<script>
    (function () {
        const typeSelect = document.getElementById('kb-type');
        const fileField = document.getElementById('kb-file-field');
        const linkField = document.getElementById('kb-link-field');

        function toggleFields() {
            if (typeSelect.value === 'link') {
                fileField.classList.add('d-none');
                linkField.classList.remove('d-none');
            } else {
                fileField.classList.remove('d-none');
                linkField.classList.add('d-none');
            }
        }

        typeSelect.addEventListener('change', toggleFields);
        toggleFields();
    })();
</script>
