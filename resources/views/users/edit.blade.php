@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <x-page-header title="Edit User" icon="bi-person-gear" :subtitle="$user->name" />
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf
                @method('PUT')
                @include('users._form', ['user' => $user])
                <div class="mt-3">
                    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Update User</button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
