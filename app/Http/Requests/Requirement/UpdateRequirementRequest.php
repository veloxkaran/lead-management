<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Rules\RichTextNotBlank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('requirement'));
    }

    public function messages(): array
    {
        return ['cancellation_reason.required' => 'Say why the requirement is being cancelled.'];
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'system_module_id' => ['required', 'exists:system_modules,id'],
            'requirement' => ['required', 'string', new RichTextNotBlank],
            'priority' => ['required', new Enum(RequirementPriority::class)],
            'status' => ['required', new Enum(RequirementStatus::class)],
            'cancellation_reason' => [
                Rule::requiredIf(fn () => $this->input('status') === RequirementStatus::Cancelled->value),
                'nullable', 'string', 'max:2000',
            ],
            'due_date' => ['nullable', 'date'],
            'client_acknowledged_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:pdf,docx,xls,xlsx,csv,jpg,jpeg,png,gif,webp'],
        ];
    }
}
