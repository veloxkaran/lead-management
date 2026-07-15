<?php

namespace App\Http\Controllers;

use App\Models\Department;

/**
 * Shared by SopController and DepartmentJdController — both are assigned to
 * exactly one Department and validate/render identically.
 */
abstract class DepartmentAssignedPolicyDocumentController extends PolicyDocumentTypeController
{
    protected function storeRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'content' => ['required', 'string'],
            'effective_date' => ['required', 'date'],
            'version' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
        ];
    }

    protected function formData(): array
    {
        return ['departments' => Department::orderBy('name')->get()];
    }
}
