<?php

namespace App\Http\Requests\ReleaseNote;

use App\Models\ReleaseNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreReleaseNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ReleaseNote::class);
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'release_date' => ['required', 'date'],
            'google_drive_video_link' => ['nullable', 'url', 'max:2048'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:20480'],
        ];
    }
}
