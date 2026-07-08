@extends('layouts.app')

@section('title', 'Edit Lead')

@section('content')
    <x-page-header title="Edit Lead" icon="bi-diagram-3" :subtitle="$lead->company_name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('leads.update', $lead) }}">
                @csrf
                @method('PUT')
                @include('leads._form', ['lead' => $lead])
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Lead</button>
                    <a href="{{ route('leads.show', $lead) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
