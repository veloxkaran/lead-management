<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Http\Requests\StoreLeadActivityRequest;
use App\Models\Lead;
use App\Services\LeadActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(protected LeadActivityService $activityService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\LeadActivity::class);

        $filters = $request->only(['activity_type', 'from', 'to']);

        if (! $request->user()->isOverseer()) {
            $filters['visible_to_user_id'] = $request->user()->id;
        }

        $activities = $this->activityService->list($filters, 20);

        return view('activities.index', [
            'activities' => $activities,
            'activityTypes' => ActivityType::cases(),
            'filters' => $request->only(['activity_type', 'from', 'to']),
        ]);
    }

    public function store(StoreLeadActivityRequest $request, Lead $lead): RedirectResponse
    {
        $this->activityService->logForLead($lead, $request->validated(), $request->user());

        return back()->with('success', 'Activity logged successfully.');
    }
}
