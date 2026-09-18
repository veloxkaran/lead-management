<?php

namespace App\Http\Controllers;

use App\Http\Requests\Requirement\UpdateRequirementStatusRequest;
use App\Models\Requirement;
use App\Services\RequirementService;
use Illuminate\Http\RedirectResponse;

class RequirementStatusController extends Controller
{
    public function __construct(protected RequirementService $requirementService)
    {
    }

    public function update(UpdateRequirementStatusRequest $request, Requirement $requirement): RedirectResponse
    {
        $this->requirementService->updateStatus(
            $requirement,
            $request->validated('status'),
            $request->validated('note'),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('success', 'Status updated.');
    }
}
