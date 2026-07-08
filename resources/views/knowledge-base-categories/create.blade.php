@extends('layouts.app')

@section('title', 'New Category')

@section('content')
    <x-page-header title="New Category" icon="bi-folder2" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('knowledge-base-categories.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Category</button>
                <a href="{{ route('knowledge-base-categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
