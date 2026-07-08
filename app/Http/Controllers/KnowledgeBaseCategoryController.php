<?php

namespace App\Http\Controllers;

use App\Http\Requests\KnowledgeBaseCategory\StoreKnowledgeBaseCategoryRequest;
use App\Http\Requests\KnowledgeBaseCategory\UpdateKnowledgeBaseCategoryRequest;
use App\Models\KnowledgeBaseCategory;
use App\Services\KnowledgeBaseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KnowledgeBaseCategoryController extends Controller
{
    public function __construct(protected KnowledgeBaseCategoryService $categoryService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', KnowledgeBaseCategory::class);

        return view('knowledge-base-categories.index', [
            'categories' => $this->categoryService->list(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', KnowledgeBaseCategory::class);

        return view('knowledge-base-categories.create');
    }

    public function store(StoreKnowledgeBaseCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()->route('knowledge-base-categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(KnowledgeBaseCategory $knowledge_base_category): View
    {
        $this->authorize('update', $knowledge_base_category);

        return view('knowledge-base-categories.edit', ['category' => $knowledge_base_category]);
    }

    public function update(UpdateKnowledgeBaseCategoryRequest $request, KnowledgeBaseCategory $knowledge_base_category): RedirectResponse
    {
        $this->categoryService->update($knowledge_base_category, $request->validated());

        return redirect()->route('knowledge-base-categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(KnowledgeBaseCategory $knowledge_base_category): RedirectResponse
    {
        $this->authorize('delete', $knowledge_base_category);

        if ($knowledge_base_category->items()->exists()) {
            return back()->with('error', 'This category still has knowledge base items and cannot be deleted.');
        }

        $this->categoryService->delete($knowledge_base_category);

        return redirect()->route('knowledge-base-categories.index')->with('success', 'Category deleted successfully.');
    }
}
