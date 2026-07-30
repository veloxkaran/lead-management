<?php

namespace App\Http\Requests\RawData;

use App\Models\RawData;
use App\Rules\NotDuplicateRawContact;
use Illuminate\Foundation\Http\FormRequest;

class StoreRawDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RawData::class);
    }

    public function rules(): array
    {
        return [
            'contact_person' => ['nullable', 'string', 'max:255', new NotDuplicateRawContact('contact_person')],
            'company_name' => ['nullable', 'string', 'max:255'],
            'number_of_employees' => ['nullable', 'integer', 'min:0'],
            'phone' => ['nullable', 'string', 'max:30', new NotDuplicateRawContact('phone')],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
