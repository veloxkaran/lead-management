@extends('layouts.app')

@section('title', 'Edit Knowledge Base Item')

@section('content')
    <x-page-header title="Edit Knowledge Base Item" icon="bi-journal-bookmark" :subtitle="$item->title" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('knowledge-base.update', $item) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('knowledge-base._form', ['item' => $item])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Item</button>
                    <a href="{{ route('knowledge-base.show', $item) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
