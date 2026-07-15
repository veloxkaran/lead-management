@extends('layouts.app')

@section('title', 'Send Account Request')

@section('content')
    <x-page-header title="Send Account Request" icon="bi-cash-coin" subtitle="Send an invoicing or payment request to Finance." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('account-requests.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Lead</label>
                        <select name="lead_id" class="form-select" data-select2 required>
                            <option value=""></option>
                            @foreach ($leads as $lead)
                                <option value="{{ $lead->id }}" @selected(old('lead_id') == $lead->id)>{{ $lead->company_name }}</option>
                            @endforeach
                        </select>
                        @error('lead_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Request Type</label>
                        <select name="request_type" class="form-select">
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('request_type', 'invoice') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('request_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Amount</label>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control" value="{{ old('amount') }}">
                        @error('amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Details</label>
                        <textarea name="details" rows="4" class="form-control" placeholder="Agreed terms, payment schedule, anything Finance needs to know.">{{ old('details') }}</textarea>
                        @error('details')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Send Request</button>
                    <a href="{{ route('account-requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
