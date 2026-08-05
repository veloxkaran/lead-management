<?php

namespace App\Http\Controllers;

use App\Enums\ActivityModule;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Http\Requests\Requirement\StoreRequirementRequest;
use App\Http\Requests\Requirement\UpdateRequirementRequest;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use App\Services\RequirementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RequirementController extends Controller
{
    public function __construct(protected RequirementService $requirementService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Requirement::class);

        $filters = $request->only(['search', 'status', 'priority', 'sprint']);

        $companies = $this->requirementService->listGroupedByCompany($filters, 15);

        return view('requirements.index', [
            'companies' => $companies,
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
            'sprints' => Requirement::sprintOptions(),
            'filters' => $filters,
        ]);
    }

    public function company(Lead $lead): View
    {
        $this->authorize('viewAny', Requirement::class);

        return view('requirements.company', [
            'lead' => $lead,
            'requirements' => $this->requirementService->listForCompany($lead),
        ]);
    }

    /**
     * Exports whatever the index's current search/status/priority/sprint
     * filters match — every matching row (not just the current page), so the
     * PDF reflects the exact same filtered set the user is looking at.
     */
    public function exportPdf(Request $request): Response
    {
        $this->authorize('viewAny', Requirement::class);

        $filters = $request->only(['search', 'status', 'priority', 'sprint']);

        $requirements = $this->requirementService->listAllForExport($filters);

        return Pdf::loadView('requirements.pdf', [
            'requirements' => $requirements,
            'filters' => $filters,
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
        ])->download('requirements.pdf');
    }

    public function create(): View
    {
        $this->authorize('create', Requirement::class);

        return view('requirements.create', [
            'leads' => Lead::orderBy('company_name')->get(),
            'priorities' => RequirementPriority::cases(),
            'sprints' => Requirement::sprintOptions(),
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

    /**
     * Open to anyone who can view the requirement (RequirementPolicy::view()
     * is universal), unlike edit() which stays restricted to whoever can
     * update it — this is where the comment thread and Created By/At live,
     * so it can't be gated behind update-only access.
     */
    public function show(Requirement $requirement): View
    {
        $this->authorize('view', $requirement);

        $requirement->load('lead', 'creator', 'assignee', 'comments.author');

        return view('requirements.show', [
            'requirement' => $requirement,
            'changeLog' => $this->changeLogFor($requirement),
        ]);
    }

    public function edit(Requirement $requirement): View
    {
        $this->authorize('update', $requirement);

        $requirement->load('lead', 'creator');

        return view('requirements.edit', [
            'requirement' => $requirement,
            'priorities' => RequirementPriority::cases(),
            'statuses' => RequirementStatus::cases(),
            'sprints' => Requirement::sprintOptions(),
            'users' => User::orderBy('name')->get(),
            'changeLog' => $this->changeLogFor($requirement),
        ]);
    }

    public function update(UpdateRequirementRequest $request, Requirement $requirement): RedirectResponse
    {
        $this->requirementService->update($requirement, $request->validated(), $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('requirements.index')->with('success', 'Requirement updated successfully.');
    }

    public function destroy(Requirement $requirement): RedirectResponse
    {
        $this->authorize('delete', $requirement);

        $this->requirementService->delete($requirement);

        return back()->with('success', 'Requirement deleted successfully.');
    }

    private function changeLogFor(Requirement $requirement): Collection
    {
        return ActivityLogEntry::where('module', ActivityModule::Requirement)
            ->where('subject_type', $requirement->getMorphClass())
            ->where('subject_id', $requirement->id)
            ->whereNotNull('new_values')
            ->with('user')
            ->latest('id')
            ->get();
    }
}
