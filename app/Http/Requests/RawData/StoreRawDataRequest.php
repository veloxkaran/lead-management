<?php

namespace App\Http\Requests\RawData;

use App\Models\RawData;
use App\Rules\NotDuplicateRawContact;
use App\Support\SimilarLeadFinder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'confirm_similar_leads' => ['nullable', 'boolean'],
        ];
    }

    /**
     * A company name >= 50% similar to an existing lead's is held back
     * until the user has seen those leads and ticked "Save anyway" — too
     * loose a match to block outright (distinct companies often share
     * words), but worth a deliberate second look before duplicating one.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->boolean('confirm_similar_leads') || $validator->errors()->has('company_name')) {
                    return;
                }

                $count = app(SimilarLeadFinder::class)->find($this->input('company_name'))->count();

                if ($count > 0) {
                    $validator->errors()->add('company_name', $count === 1
                        ? 'A lead with a similar company name already exists — check it below, or tick "Save anyway".'
                        : "{$count} leads with a similar company name already exist — check them below, or tick \"Save anyway\".");
                }
            },
        ];
    }
}
