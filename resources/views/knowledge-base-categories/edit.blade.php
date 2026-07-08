@extends('layouts.app')

@section('title', 'Edit Category')

@section('content')
    <x-page-header title="Edit Category" icon="bi-folder2" :subtitle="$category->name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('knowledge-base-categories.update', $category) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" name="name" value="{{ old('name', $category->name) }}" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Category</button>
                <a href="{{ route('knowledge-base-categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
