<?php

namespace App\Http\Requests\SupportTicket;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('support_ticket'));
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'priority' => ['required', new Enum(RequirementPriority::class)],
            'status' => ['required', new Enum(RequirementStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
