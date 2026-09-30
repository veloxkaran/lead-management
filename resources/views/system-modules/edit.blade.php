@extends('layouts.app')

@section('title', 'Edit Module')

@section('content')
    <x-page-header title="Edit Module" icon="bi-grid-3x3-gap" :subtitle="$module->name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('system-modules.update', $module) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Name *</label>
                    <input type="text" name="name" value="{{ old('name', $module->name) }}" class="form-control" maxlength="255" required>
                    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Module</button>
                <a href="{{ route('system-modules.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
