@extends('layouts.app')

@section('title', 'New Individual Job Description')

@section('content')
    <x-page-header title="New Individual Job Description" icon="bi-person-badge" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('individual-jds.store') }}">
                @csrf
                @include('policy-documents._form', [
                    'document' => null,
                    'assignmentField' => 'user_id',
                    'assignmentLabel' => 'Assigned Employee',
                    'assignmentOptions' => $users,
                ])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Job Description</button>
                    <a href="{{ route('individual-jds.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
