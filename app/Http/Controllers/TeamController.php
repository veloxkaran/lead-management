<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\OrganizationHierarchyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(protected OrganizationHierarchyService $hierarchy) {}

    public function index(Request $request): View
    {
        $this->authorize('viewTeamPage');

        $viewer = $request->user();
        $visibleIds = $this->hierarchy->visibleUserIds($viewer)
            ->reject(fn ($id) => $id === $viewer->id)
            ->values();

        $members = User::query()
            ->whereIn('id', $visibleIds)
            ->with(['assignedDepartment', 'team'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $stats = $this->hierarchy->getTeamStatistics($viewer);

        return view('team.index', [
            'members' => $members,
            'filters' => $request->only(['search', 'department_id']),
            'departments' => Department::orderBy('name')->get(),
            'reportedToday' => $stats['reported_today'],
            'latestSummary' => $stats['latest_summary'],
            'latestActivity' => $stats['latest_activity'],
            'weeklyPerformance' => $stats['weekly_performance'],
            'monthlyPerformance' => $stats['monthly_performance'],
            'goals' => $stats['goals'],
        ]);
    }
}
