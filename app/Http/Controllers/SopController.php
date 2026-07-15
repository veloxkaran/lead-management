<?php

namespace App\Http\Controllers;

use App\Enums\PolicyDocumentType;

class SopController extends DepartmentAssignedPolicyDocumentController
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
}
