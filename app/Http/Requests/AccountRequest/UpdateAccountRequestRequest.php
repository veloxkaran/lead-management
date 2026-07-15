<?php

namespace App\Http\Requests\AccountRequest;

use App\Enums\AccountRequestType;
use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAccountRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('account_request'));
    }

    public function rules(): array
    {
        return [
            'request_type' => ['required', new Enum(AccountRequestType::class)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'details' => ['nullable', 'string'],
            'status' => ['required', new Enum(RequirementStatus::class)],
            'processed_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
