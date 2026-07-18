<?php

namespace App\Http\Controllers;

use App\Enums\PolicyDocumentType;
use App\Models\PolicyDocument;
use App\Models\PolicyDocumentVersion;
use App\Models\User;
use App\Repositories\PolicyDocumentRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyPolicyDocumentsController extends Controller
{
    public function __construct(protected PolicyDocumentRepository $repository)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('my-policy-documents.index', [
            'sops' => $this->sectionRows($user, PolicyDocumentType::Sop),
            'individualJds' => $this->sectionRows($user, PolicyDocumentType::IndividualJd),
        ]);
    }

    public function show(PolicyDocumentVersion $policy_document_version): View
    {
        $this->authorize('view', $policy_document_version->policyDocument);

        $policy_document_version->load('policyDocument.user', 'policyDocument.creator');

        return view('my-policy-documents.show', [
            'version' => $policy_document_version,
        ]);
    }

    private function sectionRows(User $user, PolicyDocumentType $type)
    {
        return $this->repository->assignedToUserOfType($user, $type, ['creator'])
            ->map(function (PolicyDocument $document) use ($user) {
                $acknowledgment = $document->currentVersion->acknowledgmentFor($user);

                return (object) [
                    'document' => $document,
                    'version' => $document->currentVersion,
                    'is_read' => $acknowledgment?->acknowledged_at !== null,
                    'last_viewed' => $acknowledgment?->viewed_at,
                    'last_acknowledged' => $acknowledgment?->acknowledged_at,
                ];
            })
            ->values();
    }
}
