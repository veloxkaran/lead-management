@extends('layouts.app')

@section('title', 'Acknowledgment Status')

@section('content')
    <x-page-header title="{{ $document->title }}" icon="bi-people" :subtitle="'Version '.($document->currentVersion?->version ?? '—')">
        <x-slot:actions>
            <a href="{{ route('policy-documents.reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Reports</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Status</th>
                        <th>Last Viewed</th>
                        <th>Last Acknowledged</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->user->name }}</td>
                            <td>
                                @if ($row->acknowledged_at)
                                    <span class="badge bg-success-subtle text-success-emphasis">Acknowledged</span>
                                @elseif ($row->viewed_at)
                                    <span class="badge bg-warning-subtle text-warning-emphasis">Viewed only</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger-emphasis">Pending</span>
                                @endif
                            </td>
                            <td>{{ $row->viewed_at?->format('M d, Y H:i') ?? '—' }}</td>
                            <td>{{ $row->acknowledged_at?->format('M d, Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state icon="bi-people" title="No one assigned" description="This document has no department or employee assignment yet." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
