<?php

namespace App\Http\Requests\KnowledgeBase;

use App\Models\KnowledgeBaseItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeBaseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', KnowledgeBaseItem::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:knowledge_base_categories,id'],
            'type' => ['required', 'in:document,pdf,image,video,link'],
            'file' => ['required_unless:type,link', 'nullable', 'file', 'max:51200'],
            'link_url' => ['required_if:type,link', 'nullable', 'url', 'max:2048'],
            'tags' => ['nullable', 'string', 'max:500'],
        ];
    }
}
