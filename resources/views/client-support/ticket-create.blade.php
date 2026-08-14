@extends('layouts.guest')

@section('title', 'Raise a Support Ticket')

@section('content')
    <p class="text-muted small mb-3">
        Submitting a ticket for <strong>{{ $lead->company_name }}</strong>.
        Not you? <a href="#" onclick="event.preventDefault(); document.getElementById('client-support-logout-form').submit();">Switch account</a>
    </p>
    <form id="client-support-logout-form" method="POST" action="{{ route('client-support.logout') }}" class="d-none">@csrf</form>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('client-support.ticket.store') }}">
        @csrf
        <div class="mb-3">
            <label for="subject" class="form-label small fw-semibold">Subject</label>
            <input id="subject" type="text" name="subject" value="{{ old('subject') }}" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label for="details" class="form-label small fw-semibold">Details</label>
            <textarea id="details" name="details" rows="4" class="form-control">{{ old('details') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100">Submit Ticket</button>
    </form>
@endsection
