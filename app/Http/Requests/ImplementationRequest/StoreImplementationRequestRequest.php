<?php

namespace App\Http\Requests\ImplementationRequest;

use App\Models\ImplementationRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreImplementationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ImplementationRequest::class);
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'exists:leads,id'],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
