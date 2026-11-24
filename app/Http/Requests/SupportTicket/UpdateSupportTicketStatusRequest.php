<?php

namespace App\Http\Requests\SupportTicket;

use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * The Support Tickets list's quick "Change status" popup — status plus a
 * required note, like the Requirements list's (UpdateRequirementStatusRequest).
 */
class UpdateSupportTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('support_ticket'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(RequirementStatus::class)],
            // Doubles as the cancellation reason when the status is Cancelled.
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['note.required' => 'Add a note explaining the status change.'];
    }
}
