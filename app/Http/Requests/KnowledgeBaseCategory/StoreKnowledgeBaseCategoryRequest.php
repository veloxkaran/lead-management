<?php

namespace App\Http\Requests\KnowledgeBaseCategory;

use App\Models\KnowledgeBaseCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeBaseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', KnowledgeBaseCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:knowledge_base_categories,name'],
        ];
    }
}
