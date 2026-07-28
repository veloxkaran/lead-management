@extends('layouts.app')

@section('title', 'Bulk Upload')

@section('content')
    <x-page-header title="Bulk Upload" icon="bi-upload" subtitle="Choose what you'd like to import in bulk." />

    <div class="row g-3">
        @can('create', App\Models\Lead::class)
            <div class="col-md-6">
                <a href="{{ route('leads.bulk-upload.create') }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="fw-semibold"><i class="bi bi-building"></i> Leads</h5>
                            <p class="small text-muted mb-0">Import multiple leads at once with basic details.</p>
                        </div>
                    </div>
                </a>
            </div>
        @endcan
        @can('create', App\Models\RawData::class)
            <div class="col-md-6">
                <a href="{{ route('raw-data.bulk-upload.create') }}" class="text-decoration-none">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="fw-semibold"><i class="bi bi-inbox"></i> Raw Data</h5>
                            <p class="small text-muted mb-0">Import multiple raw data contacts (contact person &amp; phone).</p>
                        </div>
                    </div>
                </a>
            </div>
        @endcan
    </div>
@endsection
