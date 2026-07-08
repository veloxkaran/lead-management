@extends('layouts.app')

@section('title', 'Schedule Meeting')

@section('content')
    <x-page-header title="Schedule Meeting" icon="bi-camera-video" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('meetings.store') }}">
                @csrf
                @include('meetings._form', ['meeting' => null])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Meeting</button>
                    <a href="{{ route('meetings.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
