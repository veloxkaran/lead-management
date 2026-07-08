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

        $goals = $user->isSuperAdmin()
            ? Goal::with(['team', 'user'])->latest()->get()
            : Goal::where(function ($q) use ($user) {
                $q->where('goal_type', GoalType::Organization)
                    ->orWhere(fn ($q2) => $q2->where('goal_type', GoalType::Team)->where('team_id', $user->team_id))
                    ->orWhere(fn ($q2) => $q2->where('goal_type', GoalType::Individual)->where('user_id', $user->id));
            })->with(['team', 'user'])->latest()->get();

        return view('common-reports.goal-vs-achievement', ['goals' => $goals]);
    }

    public function teamAchievement(Request $request): View
    {
        $user = $request->user();

        $goals = Goal::where('goal_type', GoalType::Team)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('team_id', $user->team_id))
            ->with('team')->latest()->get()
            ->groupBy(fn (Goal $g) => $g->team?->name ?? 'Unassigned');

        return view('common-reports.team-achievement', ['groupedGoals' => $goals]);
    }

    public function personalAchievement(Request $request): View
    {
        $user = $request->user();

        $goals = Goal::where('goal_type', GoalType::Individual)->where('user_id', $user->id)->latest()->get();

        return view('common-reports.personal-achievement', ['goals' => $goals, 'user' => $user]);
    }
}
