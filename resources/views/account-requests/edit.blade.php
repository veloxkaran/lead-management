@extends('layouts.app')

@section('title', 'Edit Account Request')

@section('content')
    <x-page-header title="Account Request" icon="bi-cash-coin" :subtitle="$accountRequest->lead?->company_name" />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('account-requests.update', $accountRequest) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Request Type</label>
                        <select name="request_type" class="form-select">
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('request_type', $accountRequest->request_type->value) === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('request_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Amount</label>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control" value="{{ old('amount', $accountRequest->amount) }}">
                        @error('amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Details</label>
                        <textarea name="details" rows="4" class="form-control">{{ old('details', $accountRequest->details) }}</textarea>
                        @error('details')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $accountRequest->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Processed By</label>
                        <select name="processed_by" class="form-select" data-select2>
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('processed_by', $accountRequest->processed_by) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('processed_by')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Request</button>
                    @if ($accountRequest->lead)
                        <a href="{{ route('leads.show', $accountRequest->lead) }}" class="btn btn-outline-secondary">Cancel</a>
                    @else
                        <a href="{{ route('account-requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
@endsection
