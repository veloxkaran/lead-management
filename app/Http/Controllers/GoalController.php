<?php

namespace App\Http\Controllers;

use App\Http\Requests\Goal\StoreGoalRequest;
use App\Http\Requests\Goal\UpdateGoalRequest;
use App\Models\Goal;
use App\Repositories\GoalContributionRepository;
use App\Services\GoalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function __construct(
        protected GoalService $goalService,
        protected GoalContributionRepository $contributions,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Goal::class);

        $filters = $request->only(['category', 'status']);

        return view('goals.index', [
            'goals' => $this->goalService->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Goal::class);

        return view('goals.create');
    }

    public function store(StoreGoalRequest $request): RedirectResponse
    {
        $this->goalService->create($request->validated(), $request->user());

        return redirect()->route('goals.index')->with('success', 'Goal created successfully.');
    }

    public function show(Goal $goal): View
    {
        $this->authorize('view', $goal);

        return view('goals.show', [
            'goal' => $goal,
            'contributions' => $this->contributions->forGoal($goal),
        ]);
    }

    public function edit(Goal $goal): View
    {
        $this->authorize('update', $goal);

        return view('goals.edit', [
            'goal' => $goal,
        ]);
    }

    public function update(UpdateGoalRequest $request, Goal $goal): RedirectResponse
    {
        $this->goalService->update($goal, $request->validated());

        return redirect()->route('goals.index')->with('success', 'Goal updated successfully.');
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        $this->authorize('delete', $goal);

        $this->goalService->delete($goal);

        return redirect()->route('goals.index')->with('success', 'Goal deleted successfully.');
    }
}
