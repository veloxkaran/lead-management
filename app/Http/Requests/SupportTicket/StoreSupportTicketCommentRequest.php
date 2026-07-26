<?php

namespace App\Http\Requests\SupportTicket;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('support_ticket'));
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string'],
        ];
    }
}
