<?php

namespace App\Http\Requests\ClientSupport;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientSupportTicketRequest extends FormRequest
{
    /**
     * The verified-lead-session check lives in ClientSupportController
     * itself (redirects back to the verify page with a friendly message)
     * rather than here — failing authorize() would otherwise surface a bare
     * 403 error page to an external client, not a helpful public UX.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
