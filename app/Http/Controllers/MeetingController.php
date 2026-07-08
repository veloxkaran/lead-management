<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Models\Meeting;
use App\Models\Team;
use App\Services\MeetingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function __construct(protected MeetingService $meetingService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Meeting::class);

        $scope = $request->get('scope', 'mine');

        return view('meetings.index', [
            'meetings' => $this->meetingService->list($request->user(), $scope),
            'scope' => $scope,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Meeting::class);

        return view('meetings.create', [
            'teams' => Team::orderBy('name')->get(),
        ]);
    }

    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        $this->meetingService->create($request->validated(), $request->user());

        return redirect()->route('meetings.index')->with('success', 'Meeting scheduled successfully.');
    }

    public function edit(Meeting $meeting): View
    {
        $this->authorize('update', $meeting);

        return view('meetings.edit', [
            'meeting' => $meeting,
            'teams' => Team::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $this->meetingService->update($meeting, $request->validated());

        return redirect()->route('meetings.index')->with('success', 'Meeting updated successfully.');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->authorize('delete', $meeting);

        $this->meetingService->delete($meeting);

        return redirect()->route('meetings.index')->with('success', 'Meeting deleted successfully.');
    }
}
