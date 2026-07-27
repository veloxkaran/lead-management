<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('requirement'));
    }

    public function rules(): array
    {
        return [
            'requirement' => ['required', 'string'],
            'priority' => ['required', new Enum(RequirementPriority::class)],
            'status' => ['required', new Enum(RequirementStatus::class)],
            'due_date' => ['nullable', 'date'],
            'client_acknowledged_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
