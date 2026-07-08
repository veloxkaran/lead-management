<?php

namespace App\Http\Requests\KnowledgeBase;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgeBaseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('knowledge_base'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:knowledge_base_categories,id'],
            'type' => ['required', 'in:document,pdf,image,video,link'],
            // On update the file is optional: omitting it keeps the existing stored file (unless type is link).
            'file' => ['nullable', 'file', 'max:51200'],
            'link_url' => ['required_if:type,link', 'nullable', 'url', 'max:2048'],
            'tags' => ['nullable', 'string', 'max:500'],
        ];
    }
}
