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
            'contact_person' => ['required', 'string', 'max:255', new NotDuplicateRawContact('contact_person')],
            'phone' => ['required', 'string', 'max:30', new NotDuplicateRawContact('phone')],
        ];
    }
}
