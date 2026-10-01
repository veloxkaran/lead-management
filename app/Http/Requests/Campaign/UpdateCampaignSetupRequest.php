<?php

namespace App\Http\Requests\Campaign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Campaign Setup → SMS tab. Super Admin only — enforced by the
 * `super_admin` middleware on its routes.
 */
class UpdateCampaignSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        $name = ['nullable', 'string', 'max:100'];

        return [
            'sms_endpoint' => ['nullable', 'url:http,https', 'max:500'],
            'sms_method' => ['required', Rule::in(['GET', 'POST'])],
            'sms_format' => ['required', Rule::in(['form', 'json'])],
            'sms_auth_mode' => ['required', Rule::in(['param', 'header', 'bearer'])],
            'sms_auth_name' => $name,
            'sms_api_key' => ['nullable', 'string', 'max:1000'],
            'clear_sms_api_key' => ['nullable', 'boolean'],
            'sms_to_param' => ['required_with:sms_endpoint', ...$name],
            'sms_message_param' => ['required_with:sms_endpoint', ...$name],
            'sms_sender_param' => $name,
            'sms_sender_id' => $name,
            'sms_extra_params' => ['nullable', 'string', 'max:2000'],
            'sms_country_prefix' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'sms_success_path' => $name,
            'sms_success_value' => $name,
            'sms_message_id_path' => $name,
            'sms_dlr_id_param' => $name,
            'sms_dlr_status_param' => $name,
            'sms_dlr_delivered_values' => ['nullable', 'string', 'max:255'],
            'sms_dlr_failed_values' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'sms_to_param.required_with' => 'Enter the parameter name the gateway expects for the phone number.',
            'sms_message_param.required_with' => 'Enter the parameter name the gateway expects for the message text.',
            'sms_country_prefix.regex' => 'Use digits only, e.g. 977 (leave blank to send numbers as entered).',
        ];
    }
}
