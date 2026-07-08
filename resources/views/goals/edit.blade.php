@extends('layouts.app')

@section('title', 'Edit Goal')

@section('content')
    <x-page-header title="Edit Goal" icon="bi-bullseye" :subtitle="$goal->title" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('goals.update', $goal) }}">
                @csrf
                @method('PUT')
                @include('goals._form', ['goal' => $goal])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Goal</button>
                    <a href="{{ route('goals.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
