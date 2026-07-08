@extends('layouts.app')

@section('title', 'Edit Release Note')

@section('content')
    <x-page-header title="Edit Release Note" icon="bi-megaphone" :subtitle="$releaseNote->title" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('release-notes.update', $releaseNote) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('release-notes._form', ['releaseNote' => $releaseNote])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Release Note</button>
                    <a href="{{ route('release-notes.show', $releaseNote) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
