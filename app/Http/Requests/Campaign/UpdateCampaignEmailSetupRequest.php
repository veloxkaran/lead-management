<?php

namespace App\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Campaign Setup → Email tab: signature, footer and sending speed. The
 * sender isn't here — it comes from CAMPAIGN_MAIL_* in .env.
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
        return [
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
            'campaign_signature.max' => 'The signature images are far too large — use images under 50 KB in total.',
            'campaign_batch_size.between' => 'A batch can hold 1 to 1,000 emails.',
            'campaign_emails_per_minute.between' => 'Use 1 to 120 emails per minute — faster bursts get flagged as spam.',
        ];
    }

    public function attributes(): array
    {
        return [
            'campaign_batch_size' => 'batch size',
            'campaign_emails_per_minute' => 'emails per minute',
            'campaign_batch_pause_minutes' => 'pause between batches',
        ];
    }
}
