@extends('layouts.app')

@section('title', $releaseNote->title)

@section('content')
    <x-page-header :title="$releaseNote->title" icon="bi-megaphone" :subtitle="'Version ' . $releaseNote->version . ' · Released ' . $releaseNote->release_date->format('M d, Y')">
        <x-slot:actions>
            @can('update', $releaseNote)
                <a href="{{ route('release-notes.edit', $releaseNote) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('delete', $releaseNote)
                <form action="{{ route('release-notes.destroy', $releaseNote) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete release note?" data-confirm-text="This will remove the release note and its attachments.">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                </form>
            @endcan
            <a href="{{ route('release-notes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="fw-semibold">What's new</h6>
            <p style="white-space: pre-line;" class="mb-0">{{ $releaseNote->description }}</p>
        </div>
    </div>

    @if ($releaseNote->google_drive_video_link)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Demo Video</h6>
                <div class="ratio ratio-16x9">
                    <iframe src="{{ $releaseNote->google_drive_video_link }}" allow="autoplay" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    @endif

    @if ($releaseNote->attachments->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Attachments</h6>
                <ul class="list-group list-group-flush">
                    @foreach ($releaseNote->attachments as $attachment)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-paperclip me-1"></i>{{ $attachment->original_name }}</span>
                            <a href="{{ $attachment->url() }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Download</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
@endsection
