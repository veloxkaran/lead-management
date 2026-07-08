<?php

namespace App\Http\Requests\Lead;

use App\Rules\NotDuplicateLeadName;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Lead::class);
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255', new NotDuplicateLeadName],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'number_of_employees' => ['nullable', 'integer', 'min:0'],
            'business_details' => ['nullable', 'string'],
            'about_client_business' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'opportunity_cost' => ['nullable', 'numeric', 'min:0'],
            'achieved_cost' => ['nullable', 'numeric', 'min:0'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'lead_status_id' => ['nullable', 'exists:lead_statuses,id'],
        ];
    }
}
