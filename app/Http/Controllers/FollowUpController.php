<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatus;
use App\Enums\ReminderType;
use App\Http\Requests\FollowUp\StoreFollowUpRequest;
use App\Http\Requests\FollowUp\UpdateFollowUpRequest;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Services\FollowUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function __construct(protected FollowUpService $followUpService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', FollowUp::class);

        $filters = $request->only(['status', 'from', 'to']);

        if (! $request->user()->isOverseer()) {
            $filters['visible_to_user_id'] = $request->user()->id;
        }

        $followUps = $this->followUpService->list($filters, 20);

        return view('follow-ups.index', [
            'followUps' => $followUps,
            'statuses' => FollowUpStatus::cases(),
            'filters' => $request->only(['status', 'from', 'to']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', FollowUp::class);

        return view('follow-ups.create', [
            'leads' => Lead::orderBy('company_name')->get(),
            'reminderTypes' => ReminderType::cases(),
        ]);
    }

    public function store(StoreFollowUpRequest $request): RedirectResponse
    {
        $lead = Lead::findOrFail($request->validated('lead_id'));

        $this->followUpService->createForLead($lead, $request->safe()->except('lead_id'), $request->user());

        return redirect()->route('follow-ups.index')->with('success', 'Follow-up scheduled successfully.');
    }

    public function storeForLead(StoreFollowUpRequest $request, Lead $lead): RedirectResponse
    {
        $this->followUpService->createForLead($lead, $request->validated(), $request->user());

        return back()->with('success', 'Follow-up scheduled successfully.');
    }

    public function edit(FollowUp $followUp): View
    {
        $this->authorize('update', $followUp);

        $followUp->load('lead');

        return view('follow-ups.edit', [
            'followUp' => $followUp,
            'reminderTypes' => ReminderType::cases(),
            'statuses' => FollowUpStatus::cases(),
        ]);
    }

    public function update(UpdateFollowUpRequest $request, FollowUp $followUp): RedirectResponse
    {
        $this->followUpService->update($followUp, $request->validated());

        return redirect()->route('follow-ups.index')->with('success', 'Follow-up updated successfully.');
    }

    public function destroy(FollowUp $followUp): RedirectResponse
    {
        $this->authorize('delete', $followUp);

        $this->followUpService->delete($followUp);

        return back()->with('success', 'Follow-up deleted successfully.');
    }
}
