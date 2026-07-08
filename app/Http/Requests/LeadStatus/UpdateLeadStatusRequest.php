<?php

namespace App\Http\Requests\LeadStatus;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lead_status'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_default' => ['nullable', 'boolean'],
            'is_closed_won' => ['nullable', 'boolean'],
            'is_closed_lost' => ['nullable', 'boolean'],
            'is_achievement' => ['nullable', 'boolean'],
        ];
    }
}
