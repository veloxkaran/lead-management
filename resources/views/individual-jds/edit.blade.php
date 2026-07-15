@extends('layouts.app')

@section('title', 'Edit Individual Job Description')

@section('content')
    <x-page-header title="Edit Individual Job Description" icon="bi-person-badge" :subtitle="$document->title" />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('individual-jds.update', $document) }}">
                @csrf
                @method('PUT')
                @include('policy-documents._form', [
                    'document' => $document,
                    'assignmentField' => 'user_id',
                    'assignmentLabel' => 'Assigned Employee',
                    'assignmentOptions' => $users,
                ])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Job Description</button>
                    <a href="{{ route('individual-jds.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    @include('policy-documents._publish-version', ['document' => $document, 'routeName' => 'individual-jds'])
@endsection
