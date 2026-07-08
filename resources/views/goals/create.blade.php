@extends('layouts.app')

@section('title', 'Add Goal')

@section('content')
    <x-page-header title="Add Goal" icon="bi-bullseye" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('goals.store') }}">
                @csrf
                @include('goals._form', ['goal' => null])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Goal</button>
                    <a href="{{ route('goals.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
