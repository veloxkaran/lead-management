<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Repositories\GoalContributionRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommonReportController extends Controller
{
    public function __construct(protected GoalContributionRepository $contributions) {}

    public function goalVsAchievement(Request $request): View
    {
        $goals = Goal::with('creator')->latest()->get();

        return view('common-reports.goal-vs-achievement', ['goals' => $goals]);
    }

    public function myContributions(Request $request): View
    {
        $user = $request->user();

        return view('common-reports.my-contributions', [
            'contributions' => $this->contributions->forUser($user),
            'total' => $this->contributions->totalFor(['user_ids' => [$user->id]]),
            'user' => $user,
        ]);
    }
}
