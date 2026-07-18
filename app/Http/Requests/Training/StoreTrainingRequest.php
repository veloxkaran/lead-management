<?php

namespace App\Http\Requests\Training;

use App\Models\Training;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Training::class);
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'exists:leads,id'],
            'training_date' => ['nullable', 'date'],
            'trainer_name' => ['nullable', 'string', 'max:255'],
            'attendees_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
