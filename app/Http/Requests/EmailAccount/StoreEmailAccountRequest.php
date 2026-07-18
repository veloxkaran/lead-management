<?php

namespace App\Http\Requests\EmailAccount;

use App\Enums\EmailProvider;
use App\Enums\MailEncryption;
use App\Models\EmailAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEmailAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EmailAccount::class);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', new Enum(EmailProvider::class)],
            'email_address' => [
                'required', 'email', 'max:255',
                Rule::unique('email_accounts')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['required', new Enum(MailEncryption::class)],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['nullable', new Enum(MailEncryption::class)],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'signature' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
