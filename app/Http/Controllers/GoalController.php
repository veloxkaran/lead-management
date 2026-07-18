<?php

namespace App\Http\Controllers;

use App\Http\Requests\Goal\StoreGoalRequest;
use App\Http\Requests\Goal\UpdateGoalRequest;
use App\Models\Goal;
use App\Models\User;
use App\Services\GoalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function __construct(protected GoalService $goalService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Goal::class);

        $goals = $this->goalService->list(
            $request->only(['goal_type']),
            $request->user(),
        );

        return view('goals.index', [
            'goals' => $goals,
            'filters' => $request->only(['goal_type']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Goal::class);

        return view('goals.create', [
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function store(StoreGoalRequest $request): RedirectResponse
    {
        $this->goalService->create($request->validated(), $request->user());

        return redirect()->route('goals.index')->with('success', 'Goal created successfully.');
    }

    public function edit(Goal $goal): View
    {
        $this->authorize('update', $goal);

        return view('goals.edit', [
            'goal' => $goal,
            'users' => User::orderBy('name')->get(),
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
