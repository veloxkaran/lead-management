<?php

namespace App\Http\Requests\SupportTicket;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupportTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('comment'));
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string'],
        ];
    }
}
