@extends('layouts.app')

@section('title', 'Bulk Upload')

@section('content')
    <x-page-header title="Bulk Upload Raw Data" icon="bi-upload" subtitle="Import multiple raw data contacts at once from a spreadsheet." />

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-1-circle"></i> Download the template</h6>
                    <p class="small text-muted">Start from the template so your columns line up exactly with what the importer expects.</p>
                    <a href="{{ route('raw-data.bulk-upload.template') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-arrow-down"></i> Download Template
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-2-circle"></i> Upload your file</h6>
                    <p class="small text-muted mb-3">
                        Required columns: <strong>Contact Person</strong>, <strong>Phone</strong>.
                        Optional: Email, Source. Accepted formats: .xlsx, .xls, .csv.
                        A row matching an existing entry (by phone or contact person) won't create a
                        duplicate — it fills in that entry's missing Email/Source instead, without
                        overwriting anything already set.
                    </p>
                    <form method="POST" action="{{ route('raw-data.bulk-upload.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="file" class="form-control mb-2" accept=".xlsx,.xls,.csv" required>
                        @error('file')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload &amp; Import</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            @if (session('importFailures') && count(session('importFailures')))
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold text-danger">
                        <i class="bi bi-exclamation-triangle"></i> {{ count(session('importFailures')) }} row(s) skipped
                    </div>
                    <div class="card-body" style="max-height: 420px; overflow-y: auto;">
                        @foreach (session('importFailures') as $failure)
                            <div class="border-bottom pb-2 mb-2 small">
                                <span class="fw-semibold">Row {{ $failure->row() }}</span>
                                <span class="text-muted">({{ $failure->attribute() }})</span>
                                <ul class="mb-0 mt-1">
                                    @foreach ($failure->errors() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-empty-state icon="bi-upload" title="No results yet" description="Upload a file to see how many raw data entries were imported." />
            @endif
        </div>
    </div>
@endsection
