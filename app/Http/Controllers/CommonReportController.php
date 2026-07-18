<?php

namespace App\Http\Controllers;

use App\Enums\GoalType;
use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommonReportController extends Controller
{
    public function goalVsAchievement(Request $request): View
    {
        $user = $request->user();

        $goals = $user->isOverseer()
            ? Goal::with('user')->latest()->get()
            : Goal::where(function ($q) use ($user) {
                $q->where('goal_type', GoalType::Organization)
                    ->orWhere(fn ($q2) => $q2->where('goal_type', GoalType::Individual)->where('user_id', $user->id));
            })->with('user')->latest()->get();

        return view('common-reports.goal-vs-achievement', ['goals' => $goals]);
    }

    public function personalAchievement(Request $request): View
    {
        $user = $request->user();

        $goals = Goal::where('goal_type', GoalType::Individual)->where('user_id', $user->id)->latest()->get();

        return view('common-reports.personal-achievement', ['goals' => $goals, 'user' => $user]);
    }
}
