<?php

namespace App\Http\Requests\Campaign;

/**
 * The composer's "Preview" step: the same checks as sending, minus the
 * preview token it's about to hand out.
 */
class ComposePreviewRequest extends StoreCampaignRequest
{
    public function rules(): array
    {
        return collect(parent::rules())->except('preview_token')->all();
    }

    public function after(): array
    {
        return [];
    }
}
