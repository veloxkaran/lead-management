<?php

namespace App\Http\Requests\Training;

use App\Enums\TrainingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('training'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(TrainingStatus::class)],
            'training_date' => ['nullable', 'date'],
            'trainer_name' => ['nullable', 'string', 'max:255'],
            'attendees_count' => ['nullable', 'integer', 'min:0'],
            'completion_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string'],
        ];
    }
}
