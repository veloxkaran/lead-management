<?php

namespace App\Http\Requests\RawData;

use Illuminate\Foundation\Http\FormRequest;

class AssignRawDataRequest extends FormRequest
{
    /**
     * Assigning is just RawDataPolicy::update() — the service layer refuses
     * the change on its own once the entry is no longer New, so this only
     * needs to gate "can this user act on Raw Data at all."
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('raw_data'));
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
