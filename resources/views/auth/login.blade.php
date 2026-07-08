@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
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

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label small fw-semibold">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label small fw-semibold">Password</label>
            <input id="password" type="password" name="password" class="form-control" required>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">Remember me</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>
@endsection
