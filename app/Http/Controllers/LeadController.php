<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\ReminderType;
use App\Enums\RequirementPriority;
use App\Enums\TaskPriority;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\LeadService;
use App\Services\OrganizationHierarchyService;
use App\Support\LeadWalkthrough;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function __construct(
        protected LeadService $leadService,
        protected OrganizationHierarchyService $hierarchy,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $filters = $request->only(['search', 'status_id', 'assigned_user_id', 'source', 'archived']);

        // Manager and Super Admin see the full pipeline; everyone else sees
        // leads assigned to or created by anyone in their reporting
        // hierarchy (direct + indirect reports) plus their own.
        if (! $request->user()->isOverseer()) {
            $filters['visible_user_ids'] = $this->hierarchy->getAllSubordinateIds($request->user())
                ->push($request->user()->id)
                ->all();
        }

        $leads = $this->leadService->list($filters);

        return view('leads.index', [
            'leads' => $leads,
            'statuses' => LeadStatus::ordered()->get(),
            'users' => User::orderBy('name')->get(),
            'filters' => $request->only(['search', 'status_id', 'assigned_user_id', 'source', 'archived']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('leads.create', [
            'statuses' => LeadStatus::ordered()->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $lead = $this->leadService->create($request->validated(), $request->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead created successfully.');
    }

    public function show(Lead $lead): View
    {
        $this->authorize('view', $lead);

        $lead->load([
            'assignedUser', 'creator', 'status', 'dealClosure',
            'activities.creator', 'notes.author', 'notes.attachments',
            'followUps.creator', 'requirements.assignee', 'requirements.creator',
            'statusHistories.fromStatus', 'statusHistories.toStatus', 'statusHistories.changedBy',
            'latestImplementationRequest.assignee',
            'latestTraining.conductor',
            'latestSubscription.creator',
            'supportTickets.assignee',
            'tasks.assignee',
        ]);

        return view('leads.show', [
            'lead' => $lead,
            'statuses' => LeadStatus::ordered()->get(),
            'users' => User::orderBy('name')->get(),
            'activityTypes' => array_values(array_filter(ActivityType::cases(), fn (ActivityType $type) => ! in_array($type, [ActivityType::ImplementationRequest, ActivityType::TrainingUpdate, ActivityType::SubscriptionUpdate], true))),
            'reminderTypes' => ReminderType::cases(),
            'priorities' => RequirementPriority::cases(),
            'taskPriorities' => TaskPriority::cases(),
        ]);
    }

    public function walkthrough(Lead $lead): View
    {
        $this->authorize('view', $lead);

        $lead->load([
            'assignedUser', 'status', 'dealClosure',
            'activities.creator', 'notes.author', 'notes.attachments',
            'followUps.creator', 'requirements.assignee', 'requirements.creator',
            'statusHistories.fromStatus', 'statusHistories.toStatus', 'statusHistories.changedBy',
        ]);

        return view('leads.walkthrough', [
            'lead' => $lead,
            'steps' => LeadWalkthrough::build($lead),
        ]);
    }

    public function edit(Lead $lead): View
    {
        $this->authorize('update', $lead);

        $lead->load('whatsappUsers');

        return view('leads.edit', [
            'lead' => $lead,
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->leadService->update($lead, $request->validated());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated successfully.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'Lead deleted successfully.');
    }

    public function archive(Lead $lead): RedirectResponse
    {
        $this->authorize('archive', $lead);

        $this->leadService->archive($lead);

        return back()->with('success', 'Lead archived.');
    }

    public function restore(Lead $lead): RedirectResponse
    {
        $this->authorize('archive', $lead);

        $this->leadService->restore($lead);

        return back()->with('success', 'Lead restored.');
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('changeStatus', $lead);

        $validated = $request->validate([
            'lead_status_id' => ['required', 'exists:lead_statuses,id'],
        ]);

        $this->leadService->changeStatus($lead, $validated['lead_status_id'], $request->user());

        return back()->with('success', 'Lead status updated.');
    }

    public function close(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('close', $lead);

        $validated = $request->validate([
            'closed_date' => ['required', 'date'],
            'deal_value' => ['required', 'numeric', 'min:0'],
            'closing_comment' => ['nullable', 'string'],
        ]);

        $this->leadService->close($lead, $validated, $request->user());

        return back()->with('success', 'Deal closed and lead marked as converted.');
    }
}
