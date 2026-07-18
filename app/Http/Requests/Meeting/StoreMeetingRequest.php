<?php

namespace App\Http\Requests\Meeting;

use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Meeting::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'meeting_time' => ['required'],
            'meeting_link' => ['required', 'url', 'max:2048'],
            'participants' => ['nullable', 'string'],
        ];
    }
}
