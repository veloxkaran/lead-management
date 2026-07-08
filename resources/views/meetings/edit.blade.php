@extends('layouts.app')

@section('title', 'Edit Meeting')

@section('content')
    <x-page-header title="Edit Meeting" icon="bi-camera-video" :subtitle="$meeting->title" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('meetings.update', $meeting) }}">
                @csrf
                @method('PUT')
                @include('meetings._form', ['meeting' => $meeting])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Meeting</button>
                    <a href="{{ route('meetings.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
