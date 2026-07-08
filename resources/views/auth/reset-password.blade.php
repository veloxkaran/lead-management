@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="mb-3">
            <label for="email" class="form-label small fw-semibold">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label small fw-semibold">New password</label>
            <input id="password" type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label small fw-semibold">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Reset password</button>
    </form>
@endsection
