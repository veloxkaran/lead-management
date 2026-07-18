<?php

namespace App\Http\Controllers;

use App\Enums\PolicyDocumentType;

class SopController extends PolicyDocumentTypeController
{
    protected function type(): PolicyDocumentType
    {
        return PolicyDocumentType::Sop;
    }

    protected function viewPrefix(): string
    {
        return 'sops';
    }

    protected function routeName(): string
    {
        return 'sops';
    }

    protected function storeRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'effective_date' => ['required', 'date'],
            'version' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    protected function formData(): array
    {
        return [];
    }
}
