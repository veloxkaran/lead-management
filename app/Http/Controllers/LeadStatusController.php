<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadStatus\StoreLeadStatusRequest;
use App\Http\Requests\LeadStatus\UpdateLeadStatusRequest;
use App\Models\LeadStatus;
use App\Services\LeadStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeadStatusController extends Controller
{
    public function __construct(protected LeadStatusService $leadStatusService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', LeadStatus::class);

        return view('lead-statuses.index', [
            'statuses' => $this->leadStatusService->list(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LeadStatus::class);

        return view('lead-statuses.create');
    }

    public function store(StoreLeadStatusRequest $request): RedirectResponse
    {
        $this->leadStatusService->create($request->validated());

        return redirect()->route('lead-statuses.index')->with('success', 'Lead status created successfully.');
    }

    public function edit(LeadStatus $leadStatus): View
    {
        $this->authorize('update', $leadStatus);

        return view('lead-statuses.edit', ['leadStatus' => $leadStatus]);
    }

    public function update(UpdateLeadStatusRequest $request, LeadStatus $leadStatus): RedirectResponse
    {
        $this->leadStatusService->update($leadStatus, $request->validated());

        return redirect()->route('lead-statuses.index')->with('success', 'Lead status updated successfully.');
    }

    public function destroy(LeadStatus $leadStatus): RedirectResponse
    {
        $this->authorize('delete', $leadStatus);

        try {
            $this->leadStatusService->delete($leadStatus);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->collapse()->first());
        }

        return redirect()->route('lead-statuses.index')->with('success', 'Lead status deleted successfully.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('reorder', LeadStatus::class);

        $this->leadStatusService->reorder((array) $request->input('order', []));

        return redirect()->route('lead-statuses.index')->with('success', 'Lead status order updated.');
    }
}
