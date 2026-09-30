<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemModule\StoreSystemModuleRequest;
use App\Http\Requests\SystemModule\UpdateSystemModuleRequest;
use App\Models\SystemModule;
use App\Services\SystemModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemModuleController extends Controller
{
    public function __construct(protected SystemModuleService $moduleService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', SystemModule::class);

        return view('system-modules.index', [
            'modules' => $this->moduleService->list(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SystemModule::class);

        return view('system-modules.create');
    }

    public function store(StoreSystemModuleRequest $request): RedirectResponse
    {
        $this->moduleService->create($request->validated());

        return redirect()->route('system-modules.index')->with('success', 'Module created successfully.');
    }

    public function edit(SystemModule $system_module): View
    {
        $this->authorize('update', $system_module);

        return view('system-modules.edit', ['module' => $system_module]);
    }

    public function update(UpdateSystemModuleRequest $request, SystemModule $system_module): RedirectResponse
    {
        $this->moduleService->update($system_module, $request->validated());

        return redirect()->route('system-modules.index')->with('success', 'Module updated successfully.');
    }

    public function destroy(SystemModule $system_module): RedirectResponse
    {
        $this->authorize('delete', $system_module);

        if ($system_module->requirements()->exists()) {
            return back()->with('error', 'This module is still used by requirements and cannot be deleted.');
        }

        $this->moduleService->delete($system_module);

        return redirect()->route('system-modules.index')->with('success', 'Module deleted successfully.');
    }
}
