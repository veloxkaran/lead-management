@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <x-page-header :title="$user->name" icon="bi-person" :subtitle="$user->email">
        <x-slot:actions>
            @can('impersonate', $user)
                <form action="{{ route('users.impersonate', $user) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-in-right"></i> Login as {{ $user->name }}</button>
                </form>
            @endcan
            <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        </x-slot:actions>
    </x-page-header>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-3 text-muted">Phone</dt><dd class="col-9">{{ $user->phone ?: '—' }}</dd>
                <dt class="col-3 text-muted">Designation</dt><dd class="col-9">{{ $user->designation ?: '—' }}</dd>
                <dt class="col-3 text-muted">Role</dt><dd class="col-9">{{ $user->role->label() }}</dd>
                <dt class="col-3 text-muted">Status</dt><dd class="col-9"><x-status-badge :status="$user->status" /></dd>
            </dl>
        </div>
    </div>
@endsection
