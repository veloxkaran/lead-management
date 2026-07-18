<?php

namespace App\Http\Requests\Subscription;

use App\Enums\BillingCycle;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Subscription::class);
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'exists:leads,id'],
            'plan_name' => ['required', 'string', 'max:255'],
            'contract_start_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'licensed_users' => ['nullable', 'integer', 'min:0'],
            'active_users' => ['nullable', 'integer', 'min:0'],
            'billing_cycle' => ['nullable', new Enum(BillingCycle::class)],
            'renewal_amount' => ['nullable', 'numeric', 'min:0'],
            'auto_renew' => ['nullable', 'boolean'],
            'last_payment_date' => ['nullable', 'date'],
            'outstanding_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
