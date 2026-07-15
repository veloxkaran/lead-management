@extends('layouts.app')

@section('title', 'Edit SOP')

@section('content')
    <x-page-header title="Edit SOP" icon="bi-journal-check" :subtitle="$document->title" />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('sops.update', $document) }}">
                @csrf
                @method('PUT')
                @include('policy-documents._form', [
                    'document' => $document,
                    'assignmentField' => 'department_id',
                    'assignmentLabel' => 'Department',
                    'assignmentOptions' => $departments,
                ])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update SOP</button>
                    <a href="{{ route('sops.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    @include('policy-documents._publish-version', ['document' => $document, 'routeName' => 'sops'])
@endsection
