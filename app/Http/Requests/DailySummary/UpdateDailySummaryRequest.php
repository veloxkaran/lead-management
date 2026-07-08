<?php

namespace App\Http\Requests\DailySummary;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('daily_summary'));
    }

    public function rules(): array
    {
        return [
            'summary_date' => ['required', 'date'],
            'achieved_today' => ['required', 'string'],
            'planned_tomorrow' => ['required', 'string'],
            'blockers' => ['nullable', 'string'],
        ];
    }
}
