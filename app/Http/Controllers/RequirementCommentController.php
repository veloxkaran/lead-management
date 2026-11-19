<?php

namespace App\Http\Controllers;

use App\Http\Requests\Requirement\StoreRequirementCommentRequest;
use App\Models\Requirement;
use App\Services\RequirementService;
use Illuminate\Http\RedirectResponse;
use App\Services\MentionNotifier;

class RequirementCommentController extends Controller
{
    public function __construct(protected RequirementService $requirementService)
    {
    }

    public function store(StoreRequirementCommentRequest $request, Requirement $requirement, MentionNotifier $mentions): RedirectResponse
    {
        $this->requirementService->addComment($requirement, $request->validated(), $request->user());
        $mentions->notify($request->validated('comment'), $request->user(), $requirement);

        return back()->with('success', 'Comment added.');
    }
}
