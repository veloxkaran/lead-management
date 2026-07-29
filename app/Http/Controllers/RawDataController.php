<?php

namespace App\Http\Controllers;

use App\Http\Requests\RawData\AssignRawDataRequest;
use App\Http\Requests\RawData\ConvertRawDataRequest;
use App\Http\Requests\RawData\StoreRawDataRequest;
use App\Models\RawData;
use App\Models\User;
use App\Services\RawDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RawDataController extends Controller
{
    public function __construct(protected RawDataService $rawDataService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', RawData::class);

        $filters = $request->only(['search', 'status']);

        return view('raw-data.index', [
            'entries' => $this->rawDataService->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', RawData::class);

        return view('raw-data.create');
    }

    public function store(StoreRawDataRequest $request): RedirectResponse
    {
        $rawData = $this->rawDataService->create($request->validated(), $request->user());

        return redirect()->route('raw-data.show', $rawData)->with('success', 'Raw data entry created successfully.');
    }

    public function show(RawData $rawData): View
    {
        $this->authorize('view', $rawData);

        $rawData->load(['creator', 'convertedLead', 'comments.author', 'assignee', 'assignedBy']);

        return view('raw-data.show', [
            'rawData' => $rawData,
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function destroy(RawData $rawData): RedirectResponse
    {
        $this->authorize('delete', $rawData);

        $this->rawDataService->delete($rawData);

        return redirect()->route('raw-data.index')->with('success', 'Raw data entry deleted successfully.');
    }

    public function markNotValid(RawData $rawData): RedirectResponse
    {
        $this->authorize('update', $rawData);

        $this->rawDataService->markNotValid($rawData);

        return back()->with('success', 'Raw data entry marked as not valid.');
    }

    public function convert(ConvertRawDataRequest $request, RawData $rawData): RedirectResponse
    {
        $lead = $this->rawDataService->convertToLead($rawData, $request->validated(), $request->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Raw data entry converted to lead successfully.');
    }

    public function assign(AssignRawDataRequest $request, RawData $rawData): RedirectResponse
    {
        $this->rawDataService->assign($rawData, $request->validated('assigned_to'), $request->user());

        return back()->with('success', 'Raw data entry assignment updated.');
    }
}
