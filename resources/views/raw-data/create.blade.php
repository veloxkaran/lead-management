@extends('layouts.app')

@section('title', 'Add Raw Data')

@section('content')
    <x-page-header title="Add Raw Data" icon="bi-inbox" subtitle="Quickly capture a contact person and phone number." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('raw-data.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Contact Person (optional)</label>
                        <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}">
                        @error('contact_person')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Company Name (optional)</label>
                        <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}">
                        @error('company_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Number of Employees (optional)</label>
                        <input type="number" min="0" name="number_of_employees" class="form-control" value="{{ old('number_of_employees') }}">
                        @error('number_of_employees')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone (optional)</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email (optional)</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                        @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Source (optional)</label>
                        <input type="text" name="source" class="form-control" maxlength="20" value="{{ old('source') }}">
                        @error('source')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Notes (optional)</label>
                        <textarea name="notes" rows="3" class="form-control" maxlength="2000">{{ old('notes') }}</textarea>
                        @error('notes')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
                    <a href="{{ route('raw-data.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
