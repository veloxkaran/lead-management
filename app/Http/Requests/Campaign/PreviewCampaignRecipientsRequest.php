<?php

namespace App\Http\Requests\Campaign;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;

class PreviewCampaignRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Campaign::class);
    }

    public function rules(): array
    {
        return StoreCampaignRequest::recipientRules();
    }
}
