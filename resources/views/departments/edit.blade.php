@extends('layouts.app')

@section('title', 'Edit Department')

@section('content')
    <x-page-header title="Edit Department" icon="bi-diagram-2" :subtitle="$department->name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('departments.update', $department) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" name="name" value="{{ old('name', $department->name) }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" rows="3" class="form-control">{{ old('description', $department->description) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Department</button>
                <a href="{{ route('departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
