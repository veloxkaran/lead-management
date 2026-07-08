@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <p class="text-muted small">Enter your email and we'll send you a password reset link.</p>

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

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label small fw-semibold">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100">Email password reset link</button>
    </form>
    <div class="text-center mt-3">
        <a href="{{ route('login') }}" class="small">Back to sign in</a>
    </div>
@endsection
