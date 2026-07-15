<?php

namespace App\Http\Controllers;

use App\Enums\PolicyDocumentType;

class DepartmentJdController extends DepartmentAssignedPolicyDocumentController
{
    protected function type(): PolicyDocumentType
    {
        return PolicyDocumentType::DepartmentJd;
    }

    protected function viewPrefix(): string
    {
        return 'department-jds';
    }

    protected function routeName(): string
    {
        return 'department-jds';
    }
}
