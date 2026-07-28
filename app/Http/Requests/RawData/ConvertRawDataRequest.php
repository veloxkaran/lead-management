<?php

namespace App\Http\Requests\RawData;

use App\Rules\NotDuplicateLeadName;
use Illuminate\Foundation\Http\FormRequest;

class ConvertRawDataRequest extends FormRequest
{
    /**
     * Converting is just RawDataPolicy::update() — the service layer
     * refuses the transition on its own once the entry is no longer New,
     * so this only needs to gate "can this user act on Raw Data at all."
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('raw_data'));
    }

    /**
     * Company Name is the only field Raw Data doesn't already have that a
     * Lead requires — Contact Person/Phone come pre-filled from the entry
     * but stay editable here in case of a typo caught at conversion time.
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255', new NotDuplicateLeadName],
            'contact_person' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:20'],
        ];
    }
}
