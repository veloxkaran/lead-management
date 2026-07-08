@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <x-page-header title="Reports" icon="bi-bar-chart-line" subtitle="Comprehensive reporting across leads, deals, requirements, and productivity." />

    <div class="row g-3">
        @foreach ($reports as $report)
            <div class="col-md-4 col-lg-3">
                <a href="{{ route('reports.'.$report) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-bar-graph fs-2 text-primary"></i>
                        <div class="fw-semibold mt-2 text-capitalize">{{ str_replace('-', ' ', $report) }} Report</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
