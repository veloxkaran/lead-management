<?php

namespace App\Http\Requests\DailySummary;

use App\Models\DailySummary;
use Illuminate\Foundation\Http\FormRequest;

class StoreDailySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DailySummary::class);
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
