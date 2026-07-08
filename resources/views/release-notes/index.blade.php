@extends('layouts.app')

@section('title', 'Release Notes')

@section('content')
    <x-page-header title="Release Notes" icon="bi-megaphone" subtitle="Track what's new across the platform.">
        <x-slot:actions>
            @can('create', App\Models\ReleaseNote::class)
                <a href="{{ route('release-notes.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> New Release Note
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @forelse ($releaseNotes as $releaseNote)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <span class="badge bg-primary">{{ $releaseNote->version }}</span>
                        <a href="{{ route('release-notes.show', $releaseNote) }}" class="fw-semibold text-decoration-none ms-2">{{ $releaseNote->title }}</a>
                        <div class="text-muted small mt-1">
                            Released {{ $releaseNote->release_date->format('M d, Y') }} by {{ $releaseNote->creator?->name ?? 'Unknown' }}
                            @if ($releaseNote->attachments_count)
                                &middot; <i class="bi bi-paperclip"></i> {{ $releaseNote->attachments_count }}
                            @endif
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('release-notes.show', $releaseNote) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        @can('update', $releaseNote)
                            <a href="{{ route('release-notes.edit', $releaseNote) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endcan
                        @can('delete', $releaseNote)
                            <form action="{{ route('release-notes.destroy', $releaseNote) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete release note?" data-confirm-text="This will remove the release note and its attachments.">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        @endcan
                    </div>
                </div>
                <p class="text-muted small mt-2 mb-0">{{ \Illuminate\Support\Str::limit($releaseNote->description, 200) }}</p>
            </div>
        </div>
    @empty
        <x-empty-state icon="bi-megaphone" title="No release notes yet" description="Publish your first release note to keep the team informed." />
    @endforelse

    @if ($releaseNotes->hasPages())
        {{ $releaseNotes->links() }}
    @endif
@endsection
