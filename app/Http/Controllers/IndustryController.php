<?php

namespace App\Http\Controllers;

use App\Http\Requests\Industry\StoreIndustryRequest;
use App\Http\Requests\Industry\UpdateIndustryRequest;
use App\Models\Industry;
use App\Services\IndustryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IndustryController extends Controller
{
    public function __construct(protected IndustryService $industryService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Industry::class);

        return view('industries.index', [
            'industries' => $this->industryService->list(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Industry::class);

        return view('industries.create');
    }

    public function store(StoreIndustryRequest $request): RedirectResponse
    {
        $this->industryService->create($request->validated());

        return redirect()->route('industries.index')->with('success', 'Industry created successfully.');
    }

    public function edit(Industry $industry): View
    {
        $this->authorize('update', $industry);

        return view('industries.edit', ['industry' => $industry]);
    }

    public function update(UpdateIndustryRequest $request, Industry $industry): RedirectResponse
    {
        $this->industryService->update($industry, $request->validated());

        return redirect()->route('industries.index')->with('success', 'Industry updated successfully.');
    }

    public function destroy(Industry $industry): RedirectResponse
    {
        $this->authorize('delete', $industry);

        if ($industry->leads()->exists()) {
            return back()->with('error', 'This industry is still used by leads and cannot be deleted.');
        }

        $this->industryService->delete($industry);

        return redirect()->route('industries.index')->with('success', 'Industry deleted successfully.');
    }
}
