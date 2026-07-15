<?php

namespace App\Http\Requests\AccountRequest;

use App\Enums\AccountRequestType;
use App\Models\AccountRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAccountRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AccountRequest::class);
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'exists:leads,id'],
            'request_type' => ['required', new Enum(AccountRequestType::class)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'details' => ['nullable', 'string'],
        ];
    }
}
