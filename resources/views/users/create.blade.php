@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    <x-page-header title="Add User" icon="bi-person-plus" />
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                @include('users._form', ['user' => null])
                <div class="mt-3">
                    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Create User</button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
