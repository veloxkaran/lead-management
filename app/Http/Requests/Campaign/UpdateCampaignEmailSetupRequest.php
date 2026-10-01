<?php

namespace App\Http\Requests\Campaign;

use App\Enums\MailEncryption;
use App\Support\CampaignSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Campaign Setup → Email tab: sender, signature, footer and sending speed.
 * Super Admin only — enforced by the `super_admin` middleware on its routes.
 */
class UpdateCampaignEmailSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        $smtp = Rule::requiredIf($this->input('campaign_email_mode') === 'smtp');

        return [
            'campaign_email_mode' => ['required', Rule::in(array_keys(CampaignSettings::EMAIL_MODES))],
            'campaign_email_account_id' => [
                Rule::requiredIf($this->input('campaign_email_mode') === 'account'), 'nullable', 'integer',
                Rule::exists('email_accounts', 'id')->whereNull('deleted_at')->where('is_active', true),
            ],
            'campaign_smtp_host' => [$smtp, 'nullable', 'string', 'max:255'],
            'campaign_smtp_port' => [$smtp, 'nullable', 'integer', 'between:1,65535'],
            'campaign_smtp_encryption' => [$smtp, 'nullable', Rule::enum(MailEncryption::class)],
            'campaign_smtp_username' => ['nullable', 'string', 'max:255'],
            'campaign_smtp_password' => ['nullable', 'string', 'max:1000'],
            'clear_campaign_smtp_password' => ['nullable', 'boolean'],
            'campaign_from_name' => ['nullable', 'string', 'max:100'],
            'campaign_from_address' => ['nullable', 'email', 'max:255'],
            'campaign_reply_to' => ['nullable', 'email', 'max:255'],
            // Raw editor HTML, images still inline as base64 — CampaignSignature
            // stores them (shrinking big ones) and enforces the real 50 KB limit.
            'campaign_signature' => ['nullable', 'string', 'max:3000000'],
            'campaign_footer' => ['nullable', 'string', 'max:1000'],
            'campaign_track_opens' => ['nullable', 'boolean'],
            'campaign_batch_size' => ['required', 'integer', 'between:1,1000'],
            'campaign_emails_per_minute' => ['required', 'integer', 'between:1,120'],
            'campaign_batch_pause_minutes' => ['required', 'integer', 'between:0,1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'campaign_email_account_id.required' => 'Choose the Email Account to send from.',
            'campaign_smtp_host.required' => 'Enter the SMTP server, e.g. mail.yourdomain.com.',
            'campaign_smtp_port.required' => 'Enter the SMTP port — 465 for SSL, 587 for TLS.',
            'campaign_smtp_encryption.required' => 'Choose the encryption your SMTP server uses.',
            'campaign_signature.max' => 'The signature images are far too large — use images under 50 KB in total.',
            'campaign_batch_size.between' => 'A batch can hold 1 to 1,000 emails.',
            'campaign_emails_per_minute.between' => 'Use 1 to 120 emails per minute — faster bursts get flagged as spam.',
        ];
    }

    public function attributes(): array
    {
        return [
            'campaign_from_address' => 'from address',
            'campaign_reply_to' => 'reply-to address',
            'campaign_batch_size' => 'batch size',
            'campaign_emails_per_minute' => 'emails per minute',
            'campaign_batch_pause_minutes' => 'pause between batches',
        ];
    }
}
