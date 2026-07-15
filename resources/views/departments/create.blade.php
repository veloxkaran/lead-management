@extends('layouts.app')

@section('title', 'New Department')

@section('content')
    <x-page-header title="New Department" icon="bi-diagram-2" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('departments.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" rows="3" class="form-control">{{ old('description') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Department</button>
                <a href="{{ route('departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
