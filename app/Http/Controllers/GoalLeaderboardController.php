<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Goal;
use App\Repositories\GoalContributionRepository;
use App\Services\OrganizationHierarchyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalLeaderboardController extends Controller
{
    public function __construct(
        protected GoalContributionRepository $contributions,
        protected OrganizationHierarchyService $hierarchy,
    ) {}

    /**
     * Org-wide and visible to every employee by design (transparency into
     * how the organization is performing) — the "My Team" toggle narrows
     * what a manager sees, it never restricts anyone from the full board.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $filters = $request->only(['goal_id', 'company_id', 'date_from', 'date_to']);

        if ($request->boolean('my_team')) {
            $filters['user_ids'] = $this->hierarchy->visibleUserIds($viewer)->all();
        }

        return view('goals.leaderboard', [
            'rows' => $this->contributions->leaderboard($filters),
            'total' => $this->contributions->totalFor($filters),
            'filters' => $request->only(['goal_id', 'company_id', 'date_from', 'date_to']) + ['my_team' => $request->boolean('my_team')],
            'goals' => Goal::orderBy('title')->get(),
            'companies' => $viewer->isSuperAdmin() ? Company::orderBy('name')->get() : null,
        ]);
    }
}
