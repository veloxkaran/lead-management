@extends('layouts.app')

@section('title', 'Edit Department Job Description')

@section('content')
    <x-page-header title="Edit Department Job Description" icon="bi-diagram-3" :subtitle="$document->title" />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('department-jds.update', $document) }}">
                @csrf
                @method('PUT')
                @include('policy-documents._form', [
                    'document' => $document,
                    'assignmentField' => 'department_id',
                    'assignmentLabel' => 'Department',
                    'assignmentOptions' => $departments,
                ])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Job Description</button>
                    <a href="{{ route('department-jds.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    @include('policy-documents._publish-version', ['document' => $document, 'routeName' => 'department-jds'])
@endsection
