@extends('layouts.app')

@section('title', 'Knowledge Base')

@section('content')
    <x-page-header title="Knowledge Base" icon="bi-journal-bookmark" subtitle="Shared documents, guides, and resources for the team.">
        <x-slot:actions>
            @can('create', App\Models\KnowledgeBaseItem::class)
                <a href="{{ route('knowledge-base.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Item</a>
            @endcan
            @if (auth()->user()->isSuperAdmin())
                <a href="{{ route('knowledge-base-categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-folder2"></i> Manage Categories</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Title, description, or tag">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Category</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach (\App\Enums\KnowledgeBaseType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) == $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('knowledge-base.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @if ($items->isEmpty())
        <x-empty-state icon="bi-journal-bookmark" title="No knowledge base items found" description="Try adjusting your filters or add a new item." />
    @else
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
            @foreach ($items as $item)
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <span class="badge bg-light text-dark border"><i class="bi {{ $item->type->icon() }} me-1"></i>{{ $item->type->label() }}</span>
                                <span class="badge bg-secondary">{{ $item->category->name }}</span>
                            </div>
                            <h6 class="fw-semibold">
                                <a href="{{ route('knowledge-base.show', $item) }}" class="text-decoration-none">{{ $item->title }}</a>
                            </h6>
                            @if ($item->description)
                                <p class="text-muted small mb-2">{{ \Illuminate\Support\Str::limit($item->description, 90) }}</p>
                            @endif
                            <div class="mt-auto">
                                @if ($item->tags->isNotEmpty())
                                    <div class="mb-2">
                                        @foreach ($item->tags as $tag)
                                            <span class="badge bg-light text-dark border me-1">#{{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="text-muted small">
                                    <i class="bi bi-person"></i> {{ $item->uploader?->name ?? 'Unknown' }}
                                    &middot; {{ $item->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">
            {{ $items->links() }}
        </div>
    @endif
@endsection
