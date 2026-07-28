<?php

namespace App\Http\Requests\RawData;

use Illuminate\Foundation\Http\FormRequest;

class StoreRawDataCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('raw_data'));
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string'],
        ];
    }
}
