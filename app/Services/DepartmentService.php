<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Support\Collection;

class DepartmentService
{
    public function list(): Collection
    {
        return Department::withCount(['users', 'policyDocuments'])->orderBy('name')->get();
    }

    public function create(array $attributes): Department
    {
        return Department::create($attributes);
    }

    public function update(Department $department, array $attributes): Department
    {
        $department->update($attributes);

        return $department;
    }

    public function delete(Department $department): bool
    {
        return $department->delete();
    }
}
