@extends('layouts.app')

@section('title', 'Email Details')

@section('content')
    <x-page-header title="Email Details" icon="bi-envelope-paper">
        <x-slot:actions>
            <a href="{{ route('email-logs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Email Log
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-2 text-muted">To</dt><dd class="col-10">{{ $log->to_email }}</dd>
                <dt class="col-2 text-muted">Subject</dt><dd class="col-10">{{ $log->subject }}</dd>
                <dt class="col-2 text-muted">Status</dt><dd class="col-10"><x-status-badge :status="$log->status" /></dd>
                @if ($log->error)
                    <dt class="col-2 text-muted">Error</dt><dd class="col-10 text-danger">{{ $log->error }}</dd>
                @endif
                <dt class="col-2 text-muted">Related To</dt>
                <dd class="col-10">
                    @if ($log->related instanceof \App\Models\Requirement)
                        <a href="{{ route('requirements.show', $log->related) }}">Requirement — {{ $log->related->requirement }}</a>
                    @elseif ($log->related instanceof \App\Models\SupportTicket)
                        <a href="{{ route('support-tickets.show', $log->related) }}">Support Ticket — {{ $log->related->subject }}</a>
                    @else
                        &mdash;
                    @endif
                </dd>
                <dt class="col-2 text-muted">Queued</dt><dd class="col-10">{{ $log->created_at->format('M d, Y g:i A') }}</dd>
                <dt class="col-2 text-muted">Sent</dt><dd class="col-10">{{ $log->sent_at?->format('M d, Y g:i A') ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Message</div>
        <div class="card-body">
            <p class="mb-0">{!! nl2br(e($log->body)) !!}</p>
        </div>
    </div>
@endsection
