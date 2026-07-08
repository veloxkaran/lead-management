@extends('layouts.app')

@section('title', 'Knowledge Base Categories')

@section('content')
    <x-page-header title="Knowledge Base Categories" icon="bi-folder2" subtitle="Organize knowledge base resources into categories.">
        <x-slot:actions>
            <a href="{{ route('knowledge-base-categories.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Category</a>
            <a href="{{ route('knowledge-base.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Knowledge Base</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Items</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="fw-semibold">{{ $category->name }}</td>
                            <td class="text-muted small">{{ $category->slug }}</td>
                            <td>{{ $category->items_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('knowledge-base-categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @if ($category->items_count === 0)
                                    <form action="{{ route('knowledge-base-categories.destroy', $category) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete category?" data-confirm-text="This action cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-outline-danger" disabled title="Category has items"><i class="bi bi-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state icon="bi-folder2" title="No categories yet" description="Create a category to start organizing knowledge base items." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
