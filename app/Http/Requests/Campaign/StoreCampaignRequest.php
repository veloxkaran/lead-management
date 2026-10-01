<?php

namespace App\Http\Requests\Campaign;

use App\Enums\CampaignAudience;
use App\Enums\CampaignChannel;
use App\Models\Campaign;
use App\Models\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    /** Longest SMS body accepted (about 6 standard segments). */
    public const SMS_MAX = 918;

    public const EMAIL_MAX = 10000;

    public function authorize(): bool
    {
        return $this->user()->can('create', Campaign::class);
    }

    public function rules(): array
    {
        $isEmail = $this->input('channel') === CampaignChannel::Email->value;

        return [
            ...self::recipientRules(),
            'name' => ['required', 'string', 'max:150'],
            'subject' => [Rule::requiredIf($isEmail), 'nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:'.($isEmail ? self::EMAIL_MAX : self::SMS_MAX)],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'include_signature' => ['nullable', 'boolean'],
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

    public function messages(): array
    {
        return [
            'scheduled_at.after' => 'Pick a time in the future, or leave it blank to send now.',
            'lead_status_ids.required_if' => 'Choose at least one lead status.',
            'industries.required_if' => 'Choose at least one industry.',
        ];
    }
}
