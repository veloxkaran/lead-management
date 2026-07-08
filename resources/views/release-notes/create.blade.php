@extends('layouts.app')

@section('title', 'New Release Note')

@section('content')
    <x-page-header title="New Release Note" icon="bi-megaphone" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('release-notes.store') }}" enctype="multipart/form-data">
                @csrf
                @include('release-notes._form', ['releaseNote' => null])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Release Note</button>
                    <a href="{{ route('release-notes.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
