@extends('layouts.app')

@section('title', 'Edit Industry')

@section('content')
    <x-page-header title="Edit Industry" icon="bi-buildings" :subtitle="$industry->name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('industries.update', $industry) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" name="name" value="{{ old('name', $industry->name) }}" class="form-control" maxlength="255" required>
                    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Industry</button>
                <a href="{{ route('industries.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
