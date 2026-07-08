@extends('layouts.app')

@section('title', 'Add Knowledge Base Item')

@section('content')
    <x-page-header title="Add Knowledge Base Item" icon="bi-journal-bookmark" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('knowledge-base.store') }}" enctype="multipart/form-data">
                @csrf
                @include('knowledge-base._form', ['item' => null])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Item</button>
                    <a href="{{ route('knowledge-base.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
