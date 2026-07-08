<?php

namespace App\Http\Requests;

use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreLeadActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('lead'));
    }

    public function rules(): array
    {
        return [
            'activity_type' => ['required', new Enum(ActivityType::class)],
            'activity_date' => ['required', 'date'],
            'activity_time' => ['required'],
            'description' => ['required', 'string'],
        ];
    }
}
