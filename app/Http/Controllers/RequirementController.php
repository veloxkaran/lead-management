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
use App\Services\RequirementService;
use App\Support\RequirementSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RequirementController extends Controller
{
    /**
     * Query-string filters shared by the Requirements list and its PDF export.
     */
    private const LIST_FILTERS = ['search', 'lead_id', 'status', 'priority', 'my_leads', 'view'];

    public function __construct(protected RequirementService $requirementService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Requirement::class);

        $filters = $request->only(self::LIST_FILTERS);
        $queryFilters = $this->queryFilters($filters, $request);

        return view('requirements.index', [
            'requirements' => $this->requirementService->list($queryFilters, 25),
            'summary' => $this->requirementService->summary($queryFilters),
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
            'filters' => $filters,
            'companies' => Lead::whereHas('requirements')->orderBy('company_name')->get(['id', 'company_name']),
            'leads' => Lead::active()->orderBy('company_name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function company(Lead $lead): View
    {
        $this->authorize('viewAny', Requirement::class);

        $requirements = $this->requirementService->listForCompany($lead);

        return view('requirements.company', [
            'lead' => $lead,
            'requirements' => $requirements,
            'summary' => RequirementSummary::of($requirements),
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    /**
     * Exports whatever the index's current search/company/status/priority
     * filters match — every matching row (not just the current page), so the
     * PDF reflects the exact same filtered set the user is looking at.
     */
    public function exportPdf(Request $request): Response
    {
        $this->authorize('viewAny', Requirement::class);

        $filters = $request->only(self::LIST_FILTERS);

        $requirements = $this->requirementService->listAllForExport($this->queryFilters($filters, $request));

        return Pdf::loadView('requirements.pdf', [
            'requirements' => $requirements,
            'filters' => $filters,
            'statuses' => RequirementStatus::cases(),
            'priorities' => RequirementPriority::cases(),
        ])->setPaper('a4', 'landscape')->download('requirements.pdf');
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
        $attributes = $request->safe()->except(['lead_id', 'attachments']);
        $attributes['lead_id'] = $request->validated('lead_id');

        $this->requirementService->create($attributes, $request->user(), $request->file('attachments', []));

        return redirect()->route('requirements.index')->with('success', 'Requirement created successfully.');
    }

    public function storeForLead(StoreRequirementRequest $request, Lead $lead): RedirectResponse
    {
        $this->requirementService->createForLead(
            $lead,
            $request->safe()->except('attachments'),
            $request->user(),
            $request->file('attachments', [])
        );

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

        $requirement->load('lead', 'creator', 'assignee', 'comments.author', 'attachments');

        return view('requirements.show', [
            'requirement' => $requirement,
            'changeLog' => $this->changeLogFor($requirement),
        ]);
    }

    public function assignToMe(Request $request, Requirement $requirement): RedirectResponse
    {
        $this->authorize('assignToSelf', $requirement);

        if (! $this->requirementService->assignToSelf($requirement, $request->user(), $request->ip(), $request->userAgent())) {
            return back()->with('error', 'Someone else picked up this requirement first.');
        }

        return back()->with('success', 'Requirement assigned to you.');
    }

    public function edit(Requirement $requirement): View
    {
        $this->authorize('update', $requirement);

        $requirement->load('lead', 'creator', 'attachments');

        return view('requirements.edit', [
            'requirement' => $requirement,
            'priorities' => RequirementPriority::cases(),
            'statuses' => RequirementStatus::cases(),
            'users' => User::orderBy('name')->get(),
            'changeLog' => $this->changeLogFor($requirement),
        ]);
    }

    public function update(UpdateRequirementRequest $request, Requirement $requirement): RedirectResponse
    {
        $this->requirementService->update(
            $requirement,
            $request->safe()->except('attachments'),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
            $request->file('attachments', [])
        );

        return redirect()->route('requirements.index')->with('success', 'Requirement updated successfully.');
    }

    public function destroy(Requirement $requirement): RedirectResponse
    {
        $this->authorize('delete', $requirement);

        $this->requirementService->delete($requirement);

        return back()->with('success', 'Requirement deleted successfully.');
    }

    /**
     * "My Leads" is a checkbox in the URL; the repository needs the actual
     * user id to match against each lead's assigned_user_id.
     */
    private function queryFilters(array $filters, Request $request): array
    {
        if (! empty($filters['my_leads'])) {
            $filters['lead_assigned_user_id'] = $request->user()->id;
        }

        return $filters;
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
