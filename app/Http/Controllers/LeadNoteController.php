<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Services\LeadNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\MentionNotifier;

class LeadNoteController extends Controller
{
    public function __construct(protected LeadNoteService $noteService)
    {
    }

    public function store(Request $request, Lead $lead, MentionNotifier $mentions): RedirectResponse
    {
        $this->authorize('view', $lead);

        $validated = $request->validate([
            'comment' => ['required', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $this->noteService->createForLead(
            $lead,
            $validated,
            $request->file('attachments', []),
            $request->user()
        );

        $mentions->notify($validated['comment'], $request->user(), $lead);

        return back()->with('success', 'Note added successfully.');
    }

    public function destroy(Lead $lead, LeadNote $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $this->noteService->delete($note);

        return back()->with('success', 'Note deleted successfully.');
    }
}
