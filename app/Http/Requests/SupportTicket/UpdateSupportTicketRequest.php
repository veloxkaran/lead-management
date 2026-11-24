<?php

namespace App\Http\Requests\SupportTicket;

use App\Enums\RequirementPriority;
use App\Enums\SupportTicketType;
use App\Enums\RequirementStatus;
use App\Support\RichText;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('support_ticket'));
    }

    public function messages(): array
    {
        return ['cancellation_reason.required' => 'Say why the ticket is being cancelled.'];
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', new Enum(SupportTicketType::class)],
            'details' => ['nullable', 'string'],
            'priority' => ['required', new Enum(RequirementPriority::class)],
            'status' => ['required', new Enum(RequirementStatus::class)],
            'cancellation_reason' => [
                Rule::requiredIf(fn () => $this->input('status') === RequirementStatus::Cancelled->value),
                'nullable', 'string', 'max:2000',
            ],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
        ];
    }

    /**
     * Priority/status/assignment stay editable for the ticket's whole
     * working life — only subject/details lock, and only once actually
     * changed, 12 hours after the ticket was raised. A resubmit of the
     * same unchanged values (e.g. from a readonly field) is not an edit.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ticket = $this->route('support_ticket');

            if ($ticket->detailsEditable()) {
                return;
            }

            if ($this->input('subject') !== $ticket->subject) {
                $validator->errors()->add('subject', 'The subject can no longer be edited — it locks 12 hours after the ticket is raised.');
            }

            // Compared after sanitizing: the editor re-serializes the HTML
            // (and older tickets are plain text), so the raw strings can
            // differ when nothing was actually edited. A locked edit form
            // doesn't send details at all.
            if ($this->has('details') && RichText::sanitize($this->input('details')) !== RichText::sanitize($ticket->details)) {
                $validator->errors()->add('details', 'The details can no longer be edited — they lock 12 hours after the ticket is raised.');
            }
        });
    }
}
