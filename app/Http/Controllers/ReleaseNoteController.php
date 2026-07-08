<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseNote\StoreReleaseNoteRequest;
use App\Http\Requests\ReleaseNote\UpdateReleaseNoteRequest;
use App\Models\ReleaseNote;
use App\Services\ReleaseNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReleaseNoteController extends Controller
{
    public function __construct(protected ReleaseNoteService $releaseNoteService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', ReleaseNote::class);

        return view('release-notes.index', [
            'releaseNotes' => $this->releaseNoteService->list(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ReleaseNote::class);

        return view('release-notes.create');
    }

    public function store(StoreReleaseNoteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $attachments = $data['attachments'] ?? [];
        unset($data['attachments']);

        $release_note = $this->releaseNoteService->create($data, $attachments, $request->user()->id);

        return redirect()->route('release-notes.show', $release_note)->with('success', 'Release note created successfully.');
    }

    public function show(ReleaseNote $release_note): View
    {
        $this->authorize('view', $release_note);

        $release_note->load(['creator', 'attachments']);

        return view('release-notes.show', ['releaseNote' => $release_note]);
    }

    public function edit(ReleaseNote $release_note): View
    {
        $this->authorize('update', $release_note);

        $release_note->load('attachments');

        return view('release-notes.edit', ['releaseNote' => $release_note]);
    }

    public function update(UpdateReleaseNoteRequest $request, ReleaseNote $release_note): RedirectResponse
    {
        $data = $request->validated();
        $attachments = $data['attachments'] ?? [];
        unset($data['attachments']);

        $this->releaseNoteService->update($release_note, $data, $attachments);

        return redirect()->route('release-notes.show', $release_note)->with('success', 'Release note updated successfully.');
    }

    public function destroy(ReleaseNote $release_note): RedirectResponse
    {
        $this->authorize('delete', $release_note);

        $this->releaseNoteService->delete($release_note);

        return redirect()->route('release-notes.index')->with('success', 'Release note deleted successfully.');
    }
}
