@extends('layouts.app')

@section('title', 'Raise Implementation Request')

@section('content')
    <x-page-header title="Raise Implementation Request" icon="bi-box-arrow-in-up-right" subtitle="Hand a closed deal off to Customer Success." />

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('implementation-requests.store') }}">
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
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="e.g. Onboarding &amp; setup for Acme Corp" required>
                        @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Details</label>
                        <textarea name="details" rows="4" class="form-control" placeholder="What Customer Success needs to know to get started.">{{ old('details') }}</textarea>
                        @error('details')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Planned Date</label>
                        <input type="date" name="planned_date" class="form-control" value="{{ old('planned_date') }}">
                        @error('planned_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Raise Request</button>
                    <a href="{{ route('implementation-requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
