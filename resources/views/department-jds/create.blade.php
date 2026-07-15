@extends('layouts.app')

@section('title', 'New Department Job Description')

@section('content')
    <x-page-header title="New Department Job Description" icon="bi-diagram-3" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('department-jds.store') }}">
                @csrf
                @include('policy-documents._form', [
                    'document' => null,
                    'assignmentField' => 'department_id',
                    'assignmentLabel' => 'Department',
                    'assignmentOptions' => $departments,
                ])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Job Description</button>
                    <a href="{{ route('department-jds.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
