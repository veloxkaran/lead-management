<?php

namespace App\Http\Controllers;

use App\Enums\RequirementStatus;
use App\Http\Requests\ImplementationRequest\StoreImplementationRequestRequest;
use App\Http\Requests\ImplementationRequest\UpdateImplementationRequestRequest;
use App\Models\ImplementationRequest;
use App\Models\Lead;
use App\Models\User;
use App\Services\ImplementationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImplementationRequestController extends Controller
{
    public function __construct(protected ImplementationRequestService $implementationRequests)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ImplementationRequest::class);

        $filters = $request->only(['status']);

        if ($request->user()->isBusinessDevelopment()) {
            $filters['requested_by'] = $request->user()->id;
        }

        return view('implementation-requests.index', [
            'requests' => $this->implementationRequests->list($filters, 20),
            'statuses' => RequirementStatus::cases(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ImplementationRequest::class);

        return view('implementation-requests.create', [
            'leads' => Lead::active()->orderBy('company_name')->get(),
        ]);
    }

    public function store(StoreImplementationRequestRequest $request): RedirectResponse
    {
        $this->implementationRequests->create($request->validated(), $request->user());

        return redirect()->route('implementation-requests.index')->with('success', 'Implementation request raised.');
    }

    public function edit(ImplementationRequest $implementationRequest): View
    {
        $this->authorize('update', $implementationRequest);

        $implementationRequest->load('lead', 'requester', 'assignee');

        return view('implementation-requests.edit', [
            'implementationRequest' => $implementationRequest,
            'statuses' => RequirementStatus::cases(),
            'users' => User::whereIn('role', [\App\Enums\UserRole::CustomerSuccess, \App\Enums\UserRole::Manager])->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateImplementationRequestRequest $request, ImplementationRequest $implementationRequest): RedirectResponse
    {
        $this->implementationRequests->update($implementationRequest, $request->validated(), $request->user());

        return redirect()->route('implementation-requests.index')->with('success', 'Implementation request updated.');
    }

    public function destroy(ImplementationRequest $implementationRequest): RedirectResponse
    {
        $this->authorize('delete', $implementationRequest);

        $this->implementationRequests->delete($implementationRequest);

        return back()->with('success', 'Implementation request deleted.');
    }
}
