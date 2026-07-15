@extends('layouts.app')

@section('title', 'Acknowledgment Reports')

@section('content')
    <x-page-header title="Acknowledgment Reports" icon="bi-bar-chart-line" subtitle="Read and acknowledgment status for every active SOP and Job Description." />

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Version</th>
                        <th>Assigned</th>
                        <th>Acknowledged</th>
                        <th>Viewed only</th>
                        <th>Pending</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->document->title }}</td>
                            <td><span class="badge bg-primary-subtle text-primary-emphasis">{{ $row->document->type->label() }}</span></td>
                            <td>{{ $row->document->currentVersion?->version ?? '—' }}</td>
                            <td>{{ $row->assigned_count }}</td>
                            <td><span class="text-success">{{ $row->acknowledged_count }}</span></td>
                            <td><span class="text-warning">{{ $row->viewed_only_count }}</span></td>
                            <td><span class="text-danger">{{ $row->pending_count }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('policy-documents.reports.show', $row->document) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-people"></i> Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="bi-bar-chart-line" title="Nothing to report yet" description="Active SOPs and Job Descriptions will show their acknowledgment status here." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
