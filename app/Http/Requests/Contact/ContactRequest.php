<?php

namespace App\Http\Requests\Contact;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Add / edit a contact. Every field is optional, but at least one has to
 * be filled in, and an email can only belong to one contact.
 */
class ContactRequest extends FormRequest
{
    /** Digits plus the punctuation people type in phone numbers. */
    public const PHONE_PATTERN = '/^[0-9+()\-.\s\/]{3,30}$/';

    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact ? $this->user()->can('update', $contact) : $this->user()->can('create', Contact::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(Contact::FIELDS)->mapWithKeys(function (string $field) {
            $value = trim((string) $this->input($field));

            return [$field => $value === '' ? null : ($field === 'email' ? mb_strtolower($value) : $value)];
        })->all());
    }

    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:'.self::PHONE_PATTERN],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $data = $this->only(Contact::FIELDS);

                if (collect($data)->filter()->isEmpty()) {
                    $validator->errors()->add('contact', 'Fill in at least one field — a company, a name, an email or a phone number.');

                    return;
                }

                if ($data['email'] && ! $validator->errors()->has('email') && ($existing = $this->duplicateFor($data['email']))) {
                    $validator->errors()->add('email', "This email is already saved for \"{$existing->displayName()}\".");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Use digits and + ( ) - only, e.g. +977 9800000000.',
        ];
    }

    private function duplicateFor(string $email): ?Contact
    {
        return Contact::where('email', $email)
            ->when($this->route('contact'), fn ($q, Contact $contact) => $q->whereKeyNot($contact->id))
            ->first();
    }
}
