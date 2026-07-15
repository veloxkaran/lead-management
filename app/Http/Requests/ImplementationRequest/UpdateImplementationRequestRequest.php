<?php

namespace App\Http\Requests\ImplementationRequest;

use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateImplementationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('implementation_request'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'status' => ['required', new Enum(RequirementStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
