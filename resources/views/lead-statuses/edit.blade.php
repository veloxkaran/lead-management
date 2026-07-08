@extends('layouts.app')

@section('title', 'Edit Lead Status')

@section('content')
    <x-page-header title="Edit Lead Status" icon="bi-tags" :subtitle="$leadStatus->name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('lead-statuses.update', $leadStatus) }}">
                @csrf
                @method('PUT')
                @include('lead-statuses._form', ['leadStatus' => $leadStatus])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Status</button>
                    <a href="{{ route('lead-statuses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
