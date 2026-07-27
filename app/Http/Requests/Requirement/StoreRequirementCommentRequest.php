<?php

namespace App\Http\Requests\Requirement;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequirementCommentRequest extends FormRequest
{
    /**
     * Any user who can view the requirement may comment — RequirementPolicy
     * keeps view() open to everyone, unlike update() which stays restricted
     * to the creator/assignee/super admin.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('requirement'));
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string'],
        ];
    }
}
