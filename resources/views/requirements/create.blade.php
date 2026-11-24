@extends('layouts.app')

@section('title', 'Add Requirement')

@section('content')
    <x-page-header title="Add Requirement" icon="bi-list-check" subtitle="Log a new client requirement for a lead." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('requirements.store') }}" enctype="multipart/form-data">
                @csrf
                @include('requirements._add_fields', ['leads' => $leads])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Add Requirement</button>
                    <a href="{{ route('requirements.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
