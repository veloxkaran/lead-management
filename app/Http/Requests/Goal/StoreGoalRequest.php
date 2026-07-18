<?php

namespace App\Http\Requests\Goal;

use App\Enums\GoalCategory;
use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Goal::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', new Enum(GoalCategory::class)],
            'target' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'bs_year' => ['nullable', 'integer'],
            'bs_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
