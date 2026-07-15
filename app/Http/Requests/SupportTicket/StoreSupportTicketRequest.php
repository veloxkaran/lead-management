<?php

namespace App\Http\Requests\SupportTicket;

use App\Enums\RequirementPriority;
use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SupportTicket::class);
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'exists:leads,id'],
            'subject' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'priority' => ['required', new Enum(RequirementPriority::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
