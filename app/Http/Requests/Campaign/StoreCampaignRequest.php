<?php

namespace App\Http\Requests\Campaign;

use App\Enums\CampaignAudience;
use App\Enums\CampaignChannel;
use App\Models\Campaign;
use App\Models\Industry;
use App\Support\CampaignBody;
use App\Support\CampaignPreviewToken;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignRequest extends FormRequest
{
    /** Longest SMS body accepted (about 6 standard segments). */
    public const SMS_MAX = 918;

    public const EMAIL_MAX = 10000;

    /** Editor HTML carries markup, so it's allowed more characters than plain text. */
    public const EMAIL_HTML_MAX = 60000;

    /** Images (shown in the email) and PDFs (attached) — email campaigns only. */
    public const MAX_FILES = 5;

    public const MAX_FILE_KB = 5120;

    public const MAX_TOTAL_BYTES = 10 * 1024 * 1024;

    /**
     * The per-file limit actually in force: ours, or PHP's
     * upload_max_filesize if that's lower (shared hosts often set 2 MB) —
     * a bigger file would never reach the app.
     */
    public static function maxFileBytes(): int
    {
        return min(self::MAX_FILE_KB * 1024, self::iniBytes('upload_max_filesize') ?: PHP_INT_MAX);
    }

    /**
     * The total limit actually in force: ours, or PHP's post_max_size
     * (less room for the rest of the form) if that's lower.
     */
    public static function maxTotalBytes(): int
    {
        $post = self::iniBytes('post_max_size');

        return min(self::MAX_TOTAL_BYTES, $post ? max(1024 * 1024, $post - 512 * 1024) : PHP_INT_MAX);
    }

    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Campaign::class);
    }

    /**
     * An email body from the rich-text editor (message_html) becomes the
     * sanitized message, flagged html. Plain `message` is still accepted
     * for email (older forms, integrations) and is how SMS always arrives.
     */
    protected function prepareForValidation(): void
    {
        $this->dropEmptyFileSlots();

        if ($this->input('channel') === CampaignChannel::Email->value && $this->filled('message_html')) {
            $this->merge(['message' => CampaignBody::fromEditor($this->input('message_html')), 'message_format' => 'html']);
        } else {
            $this->merge(['message_format' => 'text']);
        }
    }

    /**
     * The browser sends the "Images & PDFs" input even when nothing was
     * chosen — as an empty upload (null) or an empty value — which the
     * `file` rule would reject. Only real uploads are kept.
     */
    private function dropEmptyFileSlots(): void
    {
        // Symfony's class, not Laravel's: a real request's file bag holds
        // Symfony uploads (Laravel converts them later); tests hold Laravel's.
        $files = array_values(array_filter(
            Arr::wrap($this->files->all()['attachments'] ?? []),
            fn ($file) => $file instanceof SymfonyUploadedFile,
        ));

        $this->files->remove('attachments');
        $this->request->remove('attachments');
        $this->query->remove('attachments');
        $this->json()->remove('attachments');

        if ($files) {
            $this->files->set('attachments', $files);
        }

        $this->convertedFiles = null;
    }

    public function rules(): array
    {
        $isEmail = $this->input('channel') === CampaignChannel::Email->value;
        $isHtml = $isEmail && $this->input('message_format') === 'html';

        return [
            ...self::recipientRules(),
            'name' => ['required', 'string', 'max:150'],
            'subject' => [Rule::requiredIf($isEmail), 'nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:'.($isHtml ? self::EMAIL_HTML_MAX : ($isEmail ? self::EMAIL_MAX : self::SMS_MAX))],
            'message_html' => ['nullable', 'string', 'max:'.(self::EMAIL_HTML_MAX * 2)],
            'message_format' => ['required', 'in:text,html'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'include_signature' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => ['file', 'mimes:png,jpg,jpeg,pdf', 'mimetypes:image/png,image/jpeg,application/pdf', 'max:'.intdiv(self::maxFileBytes(), 1024)],
            'preview_token' => ['required', 'string'],
        ];
    }

    /**
     * The campaign must have been previewed exactly as submitted — see
     * CampaignPreviewToken.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                self::checkTotalSize($this, $validator);

                if ($this->filled('preview_token') && ! CampaignPreviewToken::matches($this, $this->input('preview_token'))) {
                    $validator->errors()->add('preview_token', 'The campaign changed after it was previewed — preview it again before sending.');
                }
            },
        ];
    }

    /**
     * Shared with the live recipient preview, so it validates exactly what
     * the send will use.
     */
    public static function recipientRules(): array
    {
        return [
            'channel' => ['required', Rule::enum(CampaignChannel::class)],
            'audience' => ['required', Rule::enum(CampaignAudience::class)],
            'lead_status_ids' => ['required_if:audience,'.CampaignAudience::LeadStatuses->value, 'array'],
            'lead_status_ids.*' => ['integer', 'exists:lead_statuses,id'],
            'industries' => ['required_if:audience,'.CampaignAudience::Industries->value, 'array'],
            'industries.*' => ['string', Rule::in(Industry::pluck('name')->all())],
            'lead_ids' => ['nullable', 'array', 'max:5000'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'all_contacts' => ['nullable', 'boolean'],
            'contact_ids' => ['nullable', 'array', 'max:5000'],
            'contact_ids.*' => ['integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'extra_contacts' => ['nullable', 'string', 'max:100000'],
        ];
    }

    /**
     * Every recipient gets every file, so the total is capped too.
     */
    public static function checkTotalSize(FormRequest $request, Validator $validator): void
    {
        $total = collect($request->file('attachments', []))->sum(fn ($file) => $file?->getSize() ?? 0);

        if ($total > self::maxTotalBytes()) {
            $validator->errors()->add('attachments', 'The files add up to '.number_format($total / 1048576, 1).' MB — keep them under '.self::megabytes(self::maxTotalBytes()).' in total.');
        }
    }

    public static function megabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.').' MB';
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after' => 'Pick a time in the future, or leave it blank to send now.',
            'preview_token.required' => 'Preview the campaign before sending it.',
            'message.required' => 'Write the message.',
            'attachments.max' => 'Attach at most '.self::MAX_FILES.' files.',
            'attachments.*.mimes' => 'Only PNG, JPG and PDF files can be added.',
            'attachments.*.mimetypes' => 'Only PNG, JPG and PDF files can be added.',
            'attachments.*.max' => 'Each file can be at most '.self::megabytes(self::maxFileBytes()).'.',
            'attachments.*.uploaded' => 'A file didn\'t upload — each can be at most '.self::megabytes(self::maxFileBytes()).'.',
            'lead_status_ids.required_if' => 'Choose at least one lead status.',
            'industries.required_if' => 'Choose at least one industry.',
        ];
    }
}
