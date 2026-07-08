<?php

namespace App\Http\Requests\KnowledgeBaseCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeBaseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('knowledge_base_category'));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('knowledge_base_categories', 'name')->ignore($this->route('knowledge_base_category')),
            ],
        ];
    }
}
