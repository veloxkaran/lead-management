<?php

namespace App\Http\Controllers;

use App\Enums\PolicyDocumentType;
use App\Models\PolicyDocument;
use App\Services\PolicyDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shared shape for the three Super-Admin document-type resources (Sop,
 * DepartmentJd, IndividualJd) — they're the same model and service under the
 * hood, differing only in which assignment field is required and which
 * views/routes they render. Kept as three thin controllers (not one
 * type-switching controller) to match this codebase's existing convention of
 * one controller per resource (compare KnowledgeBaseController vs
 * KnowledgeBaseCategoryController).
 */
abstract class PolicyDocumentTypeController extends Controller
{
    public function __construct(protected PolicyDocumentService $policyDocumentService)
    {
    }

    abstract protected function type(): PolicyDocumentType;

    abstract protected function viewPrefix(): string;

    abstract protected function routeName(): string;

    abstract protected function storeRules(): array;

    abstract protected function updateRules(): array;

    /**
     * Extra data for the create/edit form — the department list or the
     * employee list, depending on the concrete type.
     */
    abstract protected function formData(): array;

    public function index(): View
    {
        $this->authorize('viewAny', PolicyDocument::class);

        return view("{$this->viewPrefix()}.index", [
            'documents' => $this->policyDocumentService->list($this->type()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PolicyDocument::class);

        return view("{$this->viewPrefix()}.create", $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PolicyDocument::class);

        $validated = $this->normalizeBooleans($request, $request->validate($this->storeRules()));

        $this->policyDocumentService->create($this->type(), $validated, $request->user());

        return redirect()->route("{$this->routeName()}.index")->with('success', $this->type()->label().' created successfully.');
    }

    public function edit(PolicyDocument $policy_document): View
    {
        $this->authorize('update', $policy_document);

        return view("{$this->viewPrefix()}.edit", ['document' => $policy_document] + $this->formData());
    }

    public function update(Request $request, PolicyDocument $policy_document): RedirectResponse
    {
        $this->authorize('update', $policy_document);

        $validated = $this->normalizeBooleans($request, $request->validate($this->updateRules()));

        $this->policyDocumentService->update($policy_document, $validated);

        return redirect()->route("{$this->routeName()}.index")->with('success', $this->type()->label().' updated successfully.');
    }

    public function destroy(PolicyDocument $policy_document): RedirectResponse
    {
        $this->authorize('delete', $policy_document);

        $this->policyDocumentService->delete($policy_document);

        return redirect()->route("{$this->routeName()}.index")->with('success', $this->type()->label().' deleted successfully.');
    }

    /**
     * Publishing a new version never touches an existing version row (see
     * PolicyDocumentService::publishVersion) — this is what makes "force
     * re-acknowledgement after updates" happen automatically.
     */
    public function storeVersion(Request $request, PolicyDocument $policy_document): RedirectResponse
    {
        $this->authorize('update', $policy_document);

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string'],
            'effective_date' => ['required', 'date'],
        ]);

        $this->policyDocumentService->publishVersion($policy_document, $validated, $request->user());

        return redirect()->route("{$this->routeName()}.edit", $policy_document)
            ->with('success', 'New version published — everyone assigned will be asked to re-acknowledge.');
    }

    private function normalizeBooleans(Request $request, array $validated): array
    {
        $validated['allow_skip'] = $request->boolean('allow_skip');
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
