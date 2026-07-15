@extends('layouts.app')

@section('title', $version->policyDocument->title)

@section('content')
    <x-page-header :title="$version->policyDocument->title" icon="bi-journal-check" subtitle="Reviewing outside the required onboarding flow.">
        <x-slot:actions>
            <a href="{{ route('my-policy-documents.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <x-policy-document-reader :version="$version" />
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            axios.post('{{ route('policy-documents.view', $version) }}');
        });
    </script>
@endsection
