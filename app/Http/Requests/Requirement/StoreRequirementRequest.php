<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementPriority;
use App\Enums\UserStatus;
use App\Models\Requirement;
use App\Rules\RichTextNotBlank;
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
            'title' => ['nullable', 'string', 'max:255'],
            'system_module_id' => ['required', 'exists:system_modules,id'],
            'requirement' => ['required', 'string', new RichTextNotBlank],
            'priority' => ['required', new Enum(RequirementPriority::class)],
            'due_date' => ['nullable', 'date'],
            'client_acknowledged_at' => ['nullable', 'date'],
            // Only active members are offered in the form; a deactivated or
            // suspended one can't pick the work up.
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('status', UserStatus::Active->value)],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:pdf,docx,xls,xlsx,csv,jpg,jpeg,png,gif,webp'],
        ];

        if (! $this->route('lead')) {
            $rules['lead_id'] = ['required', 'exists:leads,id'];
        }

        return $rules;
    }
}
