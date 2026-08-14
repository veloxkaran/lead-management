@extends('layouts.guest')

@section('title', 'Ticket Submitted')

@section('content')
    <div class="text-center">
        <i class="bi bi-check-circle text-success display-5"></i>
        <h2 class="h5 fw-semibold mt-2 mb-1">Ticket #{{ $ticketId }} submitted</h2>
        <p class="text-muted small mb-4">Our team has received your ticket for {{ $lead->company_name }} and will follow up soon.</p>
        <a href="{{ route('client-support.ticket.create') }}" class="btn btn-outline-primary">Submit another ticket</a>
    </div>
@endsection
