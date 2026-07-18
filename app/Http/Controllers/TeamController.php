<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Services\OrganizationHierarchyService;
use App\Support\ActivityModules\ActivityModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(
        protected OrganizationHierarchyService $hierarchy,
        protected ActivityLogRepository $activityLog,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewTeamPage');

        $viewer = $request->user();
        $visibleIds = $this->hierarchy->visibleUserIds($viewer)
            ->reject(fn ($id) => $id === $viewer->id)
            ->values();

        $members = User::query()
            ->whereIn('id', $visibleIds)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $stats = $this->hierarchy->getTeamStatistics($viewer);

        return view('team.index', [
            'members' => $members,
            'filters' => $request->only(['search']),
            'reportedToday' => $stats['reported_today'],
            'latestSummary' => $stats['latest_summary'],
            'latestActivity' => $stats['latest_activity'],
            'weeklyPerformance' => $stats['weekly_performance'],
            'monthlyPerformance' => $stats['monthly_performance'],
            'goals' => $stats['goals'],
        ]);
    }

    /**
     * Chronological, filterable feed of everything the viewer's team has
     * done — an IC with no reports (or an overseer with no matching
     * activity yet) simply sees the empty state, same discoverability
     * rationale as index() above.
     */
    public function activities(Request $request): View
    {
        $this->authorize('viewTeamPage');

        $viewer = $request->user();
        $filters = $request->only(['user_id', 'module', 'company_id', 'date_from', 'date_to', 'search']);

        $entries = $this->activityLog->feedForTeam($viewer, $filters, (int) $request->integer('page', 1));

        $filterableUsers = User::whereIn('id', $this->hierarchy->visibleUserIds($viewer))
            ->orderBy('name')->get();

        return view('team.activities', [
            'entries' => $entries,
            'filters' => $filters,
            'filterableUsers' => $filterableUsers,
            'modules' => ActivityModuleRegistry::definitions(),
            'companies' => $viewer->isSuperAdmin() ? Company::orderBy('name')->get() : null,
        ]);
    }
}
