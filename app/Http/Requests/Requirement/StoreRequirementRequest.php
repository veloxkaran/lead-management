<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementPriority;
use App\Models\Requirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Requirement::class);
    }

    public function rules(): array
    {
        $rules = [
            'requirement' => ['required', 'string'],
            'priority' => ['required', new Enum(RequirementPriority::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];

        if (! $this->route('lead')) {
            $rules['lead_id'] = ['required', 'exists:leads,id'];
        }

        return $rules;
    }
}
