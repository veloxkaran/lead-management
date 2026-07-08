<?php

namespace App\Http\Requests\FollowUp;

use App\Enums\FollowUpStatus;
use App\Enums\ReminderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('follow_up'));
    }

    public function rules(): array
    {
        return [
            'follow_up_date' => ['required', 'date'],
            'follow_up_time' => ['required'],
            'reminder_type' => ['required', new Enum(ReminderType::class)],
            'reminder_minutes_before' => ['required', 'integer', 'min:1'],
            'status' => ['required', new Enum(FollowUpStatus::class)],
        ];
    }
}
