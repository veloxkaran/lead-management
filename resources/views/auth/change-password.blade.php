@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
    <x-page-header title="Change Password" icon="bi-shield-lock" />

    <div class="card border-0 shadow-sm" style="max-width: 480px;">
        <div class="card-body p-4">
            @if (session('status'))
                <div class="alert alert-success py-2 small">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Current password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">New password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Confirm new password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Update password</button>
            </form>
        </div>
    </div>
@endsection
