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

    @can('manageWhatsappUsers', $lead)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-whatsapp me-1"></i> WhatsApp Chat Access</div>
            <div class="card-body">
                <p class="small text-muted">Only the users selected here (and Super Admins) can see and send WhatsApp messages for this lead.</p>
                <form method="POST" action="{{ route('leads.whatsapp-users.update', $lead) }}">
                    @csrf
                    @method('PUT')
                    <select name="user_ids[]" class="form-select" data-select2 multiple>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected($lead->whatsappUsers->contains('id', $u->id))>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm mt-3"><i class="bi bi-check-lg"></i> Save Access</button>
                </form>
            </div>
        </div>
    @endcan
@endsection
