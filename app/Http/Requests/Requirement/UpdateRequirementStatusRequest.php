<?php

namespace App\Http\Requests\Requirement;

use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRequirementStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('requirement'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(RequirementStatus::class)],
            // Doubles as the cancellation reason when the status is Cancelled.
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
