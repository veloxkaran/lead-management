<?php

namespace App\Http\Requests\FollowUp;

use App\Enums\ReminderType;
use App\Models\FollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FollowUp::class);
    }

    public function rules(): array
    {
        $rules = [
            'follow_up_date' => ['required', 'date'],
            'follow_up_time' => ['required'],
            'reminder_type' => ['required', new Enum(ReminderType::class)],
            'reminder_minutes_before' => ['required', 'integer', 'min:1'],
        ];

        if (! $this->route('lead')) {
            $rules['lead_id'] = ['required', 'exists:leads,id'];
        }

        return $rules;
    }
}
