<?php

namespace App\Http\Controllers;

use App\Enums\TrainingStatus;
use App\Http\Requests\Training\StoreTrainingRequest;
use App\Http\Requests\Training\UpdateTrainingRequest;
use App\Models\Lead;
use App\Models\Training;
use App\Models\User;
use App\Services\TrainingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingController extends Controller
{
    public function __construct(protected TrainingService $trainings)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Training::class);

        return view('trainings.index', [
            'trainings' => $this->trainings->list($request->only(['status']), 20),
            'statuses' => TrainingStatus::cases(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function forLead(Request $request, Lead $lead): View
    {
        $this->authorize('viewProgressStatus', $lead);

        return view('trainings.for-lead', [
            'lead' => $lead,
            'trainings' => $this->trainings->list(['lead_id' => $lead->id], 20),
            'statuses' => TrainingStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Training::class);

        return view('trainings.create', [
            'leads' => Lead::active()->orderBy('company_name')->get(),
        ]);
    }

    public function store(StoreTrainingRequest $request): RedirectResponse
    {
        $training = $this->trainings->create($request->validated(), $request->user());

        return redirect()->route('leads.show', $training->lead_id)->with('success', 'Training scheduled.');
    }

    public function edit(Training $training): View
    {
        $this->authorize('update', $training);

        $training->load('lead', 'conductor');

        return view('trainings.edit', [
            'training' => $training,
            'statuses' => TrainingStatus::cases(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateTrainingRequest $request, Training $training): RedirectResponse
    {
        $this->trainings->update($training, $request->validated(), $request->user());

        return redirect()->route('leads.show', $training->lead_id)->with('success', 'Training updated.');
    }

    public function destroy(Training $training): RedirectResponse
    {
        $this->authorize('delete', $training);

        $this->trainings->delete($training);

        return back()->with('success', 'Training deleted.');
    }
}
