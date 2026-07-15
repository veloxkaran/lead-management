<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailySummary\StoreDailySummaryRequest;
use App\Http\Requests\DailySummary\UpdateDailySummaryRequest;
use App\Models\DailySummary;
use App\Models\User;
use App\Services\DailySummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailySummaryController extends Controller
{
    public function __construct(protected DailySummaryService $dailySummaryService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DailySummary::class);

        $filters = $request->only(['q', 'from', 'to', 'user_id']);

        $summaries = $this->dailySummaryService->list($filters, $request->user());

        return view('daily-summaries.index', [
            'summaries' => $summaries,
            'filters' => $filters,
            'users' => $request->user()->isOverseer() ? User::orderBy('name')->get() : collect(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', DailySummary::class);

        return view('daily-summaries.create');
    }

    public function store(StoreDailySummaryRequest $request): RedirectResponse
    {
        $result = $this->dailySummaryService->submitOrRedirectToEdit($request->validated(), $request->user());

        if (! $result['created']) {
            return redirect()->route('daily-summaries.edit', $result['summary'])
                ->with('error', 'You already submitted a summary for that date. You can update it below.');
        }

        return redirect()->route('daily-summaries.index')->with('success', 'Daily summary submitted successfully.');
    }

    public function edit(DailySummary $dailySummary): View
    {
        $this->authorize('update', $dailySummary);

        return view('daily-summaries.edit', ['dailySummary' => $dailySummary]);
    }

    public function update(UpdateDailySummaryRequest $request, DailySummary $dailySummary): RedirectResponse
    {
        $this->dailySummaryService->update($dailySummary, $request->validated());

        return redirect()->route('daily-summaries.index')->with('success', 'Daily summary updated successfully.');
    }
}
