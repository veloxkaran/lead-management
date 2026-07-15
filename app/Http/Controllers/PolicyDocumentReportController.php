<?php

namespace App\Http\Controllers;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\View\View;

class PolicyDocumentReportController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PolicyDocument::class);

        $rows = PolicyDocument::query()
            ->with(['department.users', 'user', 'currentVersion.acknowledgments'])
            ->active()
            ->get()
            ->map(fn (PolicyDocument $document) => $this->summarize($document));

        return view('policy-documents.reports.index', ['rows' => $rows]);
    }

    public function show(PolicyDocument $policy_document): View
    {
        $this->authorize('viewAny', PolicyDocument::class);

        $policy_document->load(['department.users', 'user', 'currentVersion.acknowledgments']);

        $assignedUsers = $policy_document->assignedUsers();

        $acknowledgmentsByUserId = $policy_document->currentVersion?->acknowledgments->keyBy('user_id') ?? collect();

        $rows = $assignedUsers->map(function (User $user) use ($acknowledgmentsByUserId) {
            $acknowledgment = $acknowledgmentsByUserId->get($user->id);

            return (object) [
                'user' => $user,
                'viewed_at' => $acknowledgment?->viewed_at,
                'acknowledged_at' => $acknowledgment?->acknowledged_at,
            ];
        })->sortByDesc(fn ($row) => $row->acknowledged_at ?? $row->viewed_at ?? now()->subCentury());

        return view('policy-documents.reports.show', [
            'document' => $policy_document,
            'rows' => $rows,
        ]);
    }

    private function summarize(PolicyDocument $document): object
    {
        $assignedCount = $document->assignedUsers()->count();

        $acknowledgments = $document->currentVersion?->acknowledgments ?? collect();
        $acknowledgedCount = $acknowledgments->whereNotNull('acknowledged_at')->count();
        $viewedOnlyCount = $acknowledgments->whereNull('acknowledged_at')->whereNotNull('viewed_at')->count();

        return (object) [
            'document' => $document,
            'assigned_count' => $assignedCount,
            'acknowledged_count' => $acknowledgedCount,
            'viewed_only_count' => $viewedOnlyCount,
            'pending_count' => max(0, $assignedCount - $acknowledgedCount),
        ];
    }
}
