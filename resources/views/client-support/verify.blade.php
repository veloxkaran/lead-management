@extends('layouts.guest')

@section('title', 'Client Support')

@section('content')
    <p class="text-muted small text-center mb-3">Enter the Support ID and PIN you were given to raise a support ticket.</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('client-support.verify') }}">
        @csrf
        <div class="mb-3">
            <label for="support_id" class="form-label small fw-semibold">Support ID</label>
            <input id="support_id" type="text" name="support_id" value="{{ old('support_id') }}" class="form-control text-uppercase" style="letter-spacing: 1px;" required autofocus autocomplete="off">
        </div>
        <div class="mb-3">
            <label for="pin" class="form-label small fw-semibold">4-digit PIN</label>
            <input id="pin" type="password" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" class="form-control" required autocomplete="off">
        </div>
        <button type="submit" class="btn btn-primary w-100">Continue</button>
    </form>
@endsection
