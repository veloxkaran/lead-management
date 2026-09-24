<?php

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public const MAX_IMAGES = 5;

    /** JPEG/PNG are scaled down on upload, so larger phone photos are fine. */
    public const MAX_IMAGE_KB = 8192;

    /** GIFs are stored as-is (to keep animation), so they stay small. */
    public const MAX_GIF_KB = 2048;

    public const MAX_DOCUMENTS = 3;

    public const MAX_DOCUMENT_KB = 5120;

    public const DOCUMENT_MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt';

    /** Guards GD's memory use while decoding (~5 bytes per pixel). */
    public const MAX_IMAGE_DIMENSION = 6000;

    public function authorize(): bool
    {
        return $this->user()->can('create', Announcement::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'lead_status_ids' => ['nullable', 'array', 'required_if:audience,'.AnnouncementAudience::LeadStatuses->value],
            'lead_status_ids.*' => ['integer', 'exists:lead_statuses,id'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_DOCUMENTS],
            'documents.*' => ['file', 'mimes:'.self::DOCUMENT_MIMES, 'max:'.self::MAX_DOCUMENT_KB],
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,gif',
                'max:'.self::MAX_IMAGE_KB,
                'dimensions:max_width='.self::MAX_IMAGE_DIMENSION.',max_height='.self::MAX_IMAGE_DIMENSION,
                function (string $attribute, mixed $file, \Closure $fail) {
                    if ($file instanceof UploadedFile && $file->getMimeType() === 'image/gif' && $file->getSize() > self::MAX_GIF_KB * 1024) {
                        $fail('GIF images must be 2MB or smaller.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'lead_status_ids.required_if' => 'Choose at least one lead status.',
            'images.max' => 'You can attach up to '.self::MAX_IMAGES.' images.',
            'images.*.max' => 'Each image must be 8MB or smaller.',
            // PHP rejected the file before Laravel saw it (upload_max_filesize).
            'images.*.uploaded' => 'This image is larger than the server accepts ('.ini_get('upload_max_filesize').'). Use a smaller image, or raise upload_max_filesize in the PHP settings.',
            'documents.max' => 'You can attach up to '.self::MAX_DOCUMENTS.' documents.',
            'documents.*.max' => 'Each document must be 5MB or smaller.',
            'documents.*.mimes' => 'Documents must be PDF, Word, Excel, PowerPoint, CSV or TXT files.',
            'documents.*.uploaded' => 'This document is larger than the server accepts ('.ini_get('upload_max_filesize').').',
            'images.*.dimensions' => 'Images can be at most '.self::MAX_IMAGE_DIMENSION.' × '.self::MAX_IMAGE_DIMENSION.' pixels.',
            'images.*.mimes' => 'Images must be JPG, PNG or GIF — other formats display poorly in email clients.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $recipients = app(AnnouncementService::class)->recipients(
                AnnouncementAudience::from($this->input('audience')),
                (array) $this->input('lead_status_ids', []),
            );

            if ($recipients->isEmpty()) {
                $validator->errors()->add('audience', 'No leads in this audience have a valid email address.');
            }
        });
    }
}
