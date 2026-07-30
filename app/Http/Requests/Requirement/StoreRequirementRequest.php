<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementPriority;
use App\Models\Requirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'due_date' => ['nullable', 'date'],
            'client_acknowledged_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'sprint' => ['nullable', Rule::in(Requirement::sprintOptions())],
        ];

        if (! $this->route('lead')) {
            $rules['lead_id'] = ['required', 'exists:leads,id'];
        }

        return $rules;
    }
}
