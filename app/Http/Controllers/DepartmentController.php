<?php

namespace App\Http\Controllers;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(protected DepartmentService $departmentService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Department::class);

        return view('departments.index', [
            'departments' => $this->departmentService->list(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('departments.create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->departmentService->create($request->validated());

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('departments.edit', ['department' => $department]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->departmentService->update($department, $request->validated());

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        if ($department->users()->exists() || $department->policyDocuments()->exists()) {
            return back()->with('error', 'This department still has users or documents assigned and cannot be deleted.');
        }

        $this->departmentService->delete($department);

        return redirect()->route('departments.index')->with('success', 'Department deleted successfully.');
    }
}
