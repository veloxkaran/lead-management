@extends('layouts.app')

@section('title', $item->title)

@section('content')
    <x-page-header :title="$item->title" icon="bi-journal-bookmark" :subtitle="$item->category->name">
        <x-slot:actions>
            @can('update', $item)
                <a href="{{ route('knowledge-base.edit', $item) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('delete', $item)
                <form action="{{ route('knowledge-base.destroy', $item) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete item?" data-confirm-text="This will remove the file and cannot be undone.">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                </form>
            @endcan
            <a href="{{ route('knowledge-base.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @switch($item->type)
                        @case(\App\Enums\KnowledgeBaseType::Pdf)
                            <div class="ratio ratio-4x3">
                                <embed src="{{ $item->url() }}" type="application/pdf">
                            </div>
                            @break

                        @case(\App\Enums\KnowledgeBaseType::Image)
                            <img src="{{ $item->url() }}" class="img-fluid rounded" alt="{{ $item->title }}">
                            @break

                        @case(\App\Enums\KnowledgeBaseType::Video)
                            <video controls class="w-100 rounded">
                                <source src="{{ $item->url() }}">
                            </video>
                            @break

                        @default
                            <div class="text-center py-5">
                                <i class="bi {{ $item->type->icon() }} display-4 text-muted mb-3 d-block"></i>
                                <a href="{{ $item->url() }}" target="_blank" class="btn btn-primary"><i class="bi bi-box-arrow-up-right"></i> Open</a>
                            </div>
                    @endswitch

                    @if ($item->description)
                        <hr>
                        <p class="mb-0">{{ $item->description }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Category</dt>
                        <dd class="col-7">{{ $item->category->name }}</dd>
                        <dt class="col-5">Type</dt>
                        <dd class="col-7">{{ $item->type->label() }}</dd>
                        <dt class="col-5">Uploaded by</dt>
                        <dd class="col-7">{{ $item->uploader?->name ?? 'Unknown' }}</dd>
                        <dt class="col-5">Uploaded on</dt>
                        <dd class="col-7">{{ $item->created_at->format('M d, Y') }}</dd>
                    </dl>
                    @if ($item->tags->isNotEmpty())
                        <hr>
                        @foreach ($item->tags as $tag)
                            <span class="badge bg-light text-dark border me-1 mb-1">#{{ $tag->name }}</span>
                        @endforeach
                    @endif
                    @if ($item->type !== \App\Enums\KnowledgeBaseType::Link)
                        <hr>
                        <a href="{{ route('knowledge-base.download', $item) }}" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-download"></i> Download</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
