<?php

namespace App\Http\Controllers;

use App\Enums\KnowledgeBaseType;
use App\Http\Requests\KnowledgeBase\StoreKnowledgeBaseItemRequest;
use App\Http\Requests\KnowledgeBase\UpdateKnowledgeBaseItemRequest;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseItem;
use App\Services\KnowledgeBaseItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeBaseController extends Controller
{
    public function __construct(protected KnowledgeBaseItemService $itemService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', KnowledgeBaseItem::class);

        $filters = $request->only(['category_id', 'type', 'q']);

        return view('knowledge-base.index', [
            'items' => $this->itemService->list($filters, 12),
            'categories' => KnowledgeBaseCategory::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', KnowledgeBaseItem::class);

        return view('knowledge-base.create', [
            'categories' => KnowledgeBaseCategory::orderBy('name')->get(),
        ]);
    }

    public function store(StoreKnowledgeBaseItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');
        $tags = $data['tags'] ?? null;
        unset($data['file'], $data['tags']);

        $item = $this->itemService->create($data, $file, $tags, $request->user()->id);

        return redirect()->route('knowledge-base.show', $item)->with('success', 'Knowledge base item created successfully.');
    }

    public function show(KnowledgeBaseItem $knowledge_base): View
    {
        $this->authorize('view', $knowledge_base);

        $knowledge_base->load(['category', 'uploader', 'tags']);

        return view('knowledge-base.show', ['item' => $knowledge_base]);
    }

    public function edit(KnowledgeBaseItem $knowledge_base): View
    {
        $this->authorize('update', $knowledge_base);

        $knowledge_base->load('tags');

        return view('knowledge-base.edit', [
            'item' => $knowledge_base,
            'categories' => KnowledgeBaseCategory::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateKnowledgeBaseItemRequest $request, KnowledgeBaseItem $knowledge_base): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');
        $tags = $data['tags'] ?? null;
        unset($data['file'], $data['tags']);

        $this->itemService->update($knowledge_base, $data, $file, $tags);

        return redirect()->route('knowledge-base.show', $knowledge_base)->with('success', 'Knowledge base item updated successfully.');
    }

    public function destroy(KnowledgeBaseItem $knowledge_base): RedirectResponse
    {
        $this->authorize('delete', $knowledge_base);

        $this->itemService->delete($knowledge_base);

        return redirect()->route('knowledge-base.index')->with('success', 'Knowledge base item deleted successfully.');
    }

    public function download(KnowledgeBaseItem $knowledge_base): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $knowledge_base);

        if ($knowledge_base->type === KnowledgeBaseType::Link) {
            return redirect()->away($knowledge_base->link_url);
        }

        return Storage::disk('public')->download($knowledge_base->disk_path, $knowledge_base->original_name);
    }
}
