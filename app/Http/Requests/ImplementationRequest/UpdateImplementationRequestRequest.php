<?php

namespace App\Http\Requests\ImplementationRequest;

use App\Enums\ImplementationStatus;
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
            'status' => ['required', new Enum(ImplementationStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'planned_date' => ['nullable', 'date'],
            'completion_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'phase' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
