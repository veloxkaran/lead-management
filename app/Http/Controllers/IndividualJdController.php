<?php

namespace App\Http\Controllers;

use App\Enums\PolicyDocumentType;
use App\Models\User;

class IndividualJdController extends PolicyDocumentTypeController
{
    protected function type(): PolicyDocumentType
    {
        return PolicyDocumentType::IndividualJd;
    }

    protected function viewPrefix(): string
    {
        return 'individual-jds';
    }

    protected function routeName(): string
    {
        return 'individual-jds';
    }

    protected function storeRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'exists:users,id'],
            'content' => ['required', 'string'],
            'effective_date' => ['required', 'date'],
            'version' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'exists:users,id'],
        ];
    }

    protected function formData(): array
    {
        return ['users' => User::orderBy('name')->get()];
    }
}
