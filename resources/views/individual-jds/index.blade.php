@extends('layouts.app')

@section('title', 'Individual Job Descriptions')

@section('content')
    <x-page-header title="Individual Job Descriptions" icon="bi-person-badge" subtitle="Published to one specific employee.">
        <x-slot:actions>
            <a href="{{ route('individual-jds.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Job Description</a>
            <a href="{{ route('policy-documents.reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart-line"></i> Acknowledgment Reports</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Assigned To</th>
                        <th>Version</th>
                        <th>Effective Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="fw-semibold">{{ $document->title }}</td>
                            <td>{{ $document->user?->name ?? '—' }}</td>
                            <td>{{ $document->currentVersion?->version ?? '—' }}</td>
                            <td>{{ $document->currentVersion?->effective_date?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                @if ($document->is_active)
                                    <span class="badge bg-success-subtle text-success-emphasis">Active</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Disabled</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('policy-documents.reports.show', $document) }}" class="btn btn-sm btn-outline-secondary" title="Acknowledgment status"><i class="bi bi-people"></i></a>
                                <a href="{{ route('individual-jds.edit', $document) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('individual-jds.destroy', $document) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete this job description?" data-confirm-text="This action cannot be undone.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-person-badge" title="No individual job descriptions yet" description="Create one and assign it to an employee." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
