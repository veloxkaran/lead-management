<?php

namespace App\Http\Requests\RawData;

use App\Models\Industry;
use App\Rules\NotDuplicateLeadName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * Company Name and Industry are the only fields Raw Data doesn't already
     * have that a Lead requires — Contact Person/Phone come pre-filled from
     * the entry but stay editable here in case of a typo caught at
     * conversion time.
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255', new NotDuplicateLeadName],
            'contact_person' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', Rule::in(Industry::pluck('name')->all())],
            'number_of_employees' => ['nullable', 'integer', 'min:0'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'industry.in' => 'Choose an industry from the list set by your administrator.',
        ];
    }
}
