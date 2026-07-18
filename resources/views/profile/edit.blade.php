@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <x-page-header title="My Profile" icon="bi-person-circle" />

    <div class="card border-0 shadow-sm" style="max-width: 560px;">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white fs-4" style="width:64px;height:64px;">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </span>
                <div>
                    <div class="fw-semibold">{{ $user->name }}</div>
                    <div class="text-muted small">{{ $user->email }} · {{ $user->role->label() }}</div>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Designation</label>
                        <input type="text" name="designation" value="{{ old('designation', $user->designation) }}" class="form-control">
                    </div>
                </div>
                <button class="btn btn-primary mt-3"><i class="bi bi-check-lg"></i> Save Changes</button>
            </form>
        </div>
    </div>
@endsection
