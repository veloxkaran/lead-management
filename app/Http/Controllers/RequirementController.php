<?php

namespace App\Http\Controllers;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Http\Requests\Requirement\StoreRequirementRequest;
use App\Http\Requests\Requirement\UpdateRequirementRequest;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use App\Services\RequirementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RequirementController extends Controller
{
    public function __construct(protected RequirementService $requirementService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Requirement::class);

        $filters = $request->only(['status', 'priority']);

        $requirements = $this->requirementService->list($filters, 20);

        return view('requirements.index', [
            'requirements' => $requirements,
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Requirement::class);

        return view('requirements.create', [
            'leads' => Lead::orderBy('company_name')->get(),
            'priorities' => RequirementPriority::cases(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function store(StoreRequirementRequest $request): RedirectResponse
    {
        $attributes = $request->safe()->except('lead_id');
        $attributes['lead_id'] = $request->validated('lead_id');

        $this->requirementService->create($attributes, $request->user());

        return redirect()->route('requirements.index')->with('success', 'Requirement created successfully.');
    }

    public function storeForLead(StoreRequirementRequest $request, Lead $lead): RedirectResponse
    {
        $this->requirementService->createForLead($lead, $request->validated(), $request->user());

        return back()->with('success', 'Requirement created successfully.');
    }

    public function edit(Requirement $requirement): View
    {
        $this->authorize('update', $requirement);

        $requirement->load('lead');

        return view('requirements.edit', [
            'requirement' => $requirement,
            'priorities' => RequirementPriority::cases(),
            'statuses' => RequirementStatus::cases(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateRequirementRequest $request, Requirement $requirement): RedirectResponse
    {
        $this->requirementService->update($requirement, $request->validated());

        return redirect()->route('requirements.index')->with('success', 'Requirement updated successfully.');
    }

    public function destroy(Requirement $requirement): RedirectResponse
    {
        $this->authorize('delete', $requirement);

        $this->requirementService->delete($requirement);

        return back()->with('success', 'Requirement deleted successfully.');
    }
}
