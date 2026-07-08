@extends('layouts.app')

@section('title', 'Add Lead Status')

@section('content')
    <x-page-header title="Add Lead Status" icon="bi-tags" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('lead-statuses.store') }}">
                @csrf
                @include('lead-statuses._form', ['leadStatus' => null])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Status</button>
                    <a href="{{ route('lead-statuses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
