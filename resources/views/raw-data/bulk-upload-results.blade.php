@extends('layouts.app')

@section('title', 'Import Results')

@section('content')
    <x-page-header title="Raw Data Import Results" icon="bi-upload" subtitle="Ran on {{ $batch->created_at->format('M d, Y g:i A') }}">
        <x-slot:actions>
            @if ($batch->rejected_count > 0)
                <a href="{{ route('raw-data.bulk-upload.batches.download', $batch) }}" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-download"></i> Download Rejected Rows
                </a>
            @endif
            <a href="{{ route('raw-data.bulk-upload.create') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Upload
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="small text-muted">Total Rows</div>
                    <div class="fs-3 fw-semibold">{{ $batch->total_rows }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="small text-muted">Successfully Imported</div>
                    <div class="fs-3 fw-semibold text-success">{{ $batch->successfulCount() }}</div>
                    <div class="small text-muted">
                        {{ $batch->imported_count }} new &middot;
                        {{ $batch->updated_count }} filled in &middot;
                        {{ $batch->unchanged_count }} already up to date
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="small text-muted">Rejected Rows</div>
                    <div class="fs-3 fw-semibold {{ $batch->rejected_count > 0 ? 'text-danger' : '' }}">{{ $batch->rejected_count }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($batch->rejected_count > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold text-danger">
                <i class="bi bi-exclamation-triangle"></i> Rejected Rows
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5rem;">Row</th>
                            <th>Error(s)</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rejections as $rejection)
                            <tr>
                                <td>{{ $rejection->row_number }}</td>
                                <td class="small">
                                    <ul class="mb-0 ps-3">
                                        @foreach ($rejection->errors as $messages)
                                            @foreach ($messages as $message)
                                                <li>{{ $message }}</li>
                                            @endforeach
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="small text-muted">
                                    {{ collect($rejection->raw_data)->map(fn ($value, $key) => "{$key}: {$value}")->implode(', ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                {{ $rejections->links() }}
            </div>
        </div>

        <p class="text-muted small mt-2">
            Download the rejected rows above, correct the flagged values, and re-upload the file
            through the upload form &mdash; the extra "Import Error" column is ignored on re-import.
        </p>
    @else
        <x-empty-state icon="bi-check-circle" title="No rejected rows" description="Every row in this import passed validation." />
    @endif
@endsection
