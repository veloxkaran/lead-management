@extends('layouts.app')

@section('title', 'Add Lead')

@section('content')
    <x-page-header title="Add Lead" icon="bi-diagram-3" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('leads.store') }}">
                @csrf
                @include('leads._form', ['lead' => null])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Lead</button>
                    <a href="{{ route('leads.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
