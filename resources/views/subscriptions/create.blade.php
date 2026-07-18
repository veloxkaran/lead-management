@extends('layouts.app')

@section('title', 'Add Subscription')

@section('content')
    <x-page-header title="Add Subscription" icon="bi-credit-card" subtitle="Record a new subscription/plan for a client." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('subscriptions.store') }}">
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
                        <label class="form-label small fw-semibold">Plan Name</label>
                        <input type="text" name="plan_name" class="form-control" value="{{ old('plan_name') }}" required>
                        @error('plan_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Billing Cycle</label>
                        <select name="billing_cycle" class="form-select">
                            @foreach ($billingCycles as $cycle)
                                <option value="{{ $cycle->value }}" @selected(old('billing_cycle') === $cycle->value)>{{ $cycle->label() }}</option>
                            @endforeach
                        </select>
                        @error('billing_cycle')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Contract Start Date</label>
                        <input type="date" name="contract_start_date" class="form-control" value="{{ old('contract_start_date') }}">
                        @error('contract_start_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Expiry / Renewal Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}">
                        @error('expiry_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Licensed Users</label>
                        <input type="number" name="licensed_users" class="form-control" min="0" value="{{ old('licensed_users') }}">
                        @error('licensed_users')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Active Users</label>
                        <input type="number" name="active_users" class="form-control" min="0" value="{{ old('active_users') }}">
                        @error('active_users')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Renewal Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">{{ \App\Support\Currency::SYMBOL }}</span>
                            <input type="number" step="0.01" min="0" name="renewal_amount" class="form-control" value="{{ old('renewal_amount') }}">
                        </div>
                        @error('renewal_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Outstanding Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">{{ \App\Support\Currency::SYMBOL }}</span>
                            <input type="number" step="0.01" min="0" name="outstanding_amount" class="form-control" value="{{ old('outstanding_amount') }}">
                        </div>
                        @error('outstanding_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Last Payment Date</label>
                        <input type="date" name="last_payment_date" class="form-control" value="{{ old('last_payment_date') }}">
                        @error('last_payment_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="auto_renew" value="1" class="form-check-input" id="auto_renew" @checked(old('auto_renew'))>
                            <label class="form-check-label small fw-semibold" for="auto_renew">Auto Renewal Enabled</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Add Subscription</button>
                    <a href="{{ route('subscriptions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
