<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRequirementStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('requirement'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(RequirementStatus::class)],
            'note' => ['required', 'string'],
        ];
    }
}
