@extends('layouts.app')

@section('title', 'Announcements')

@section('content')
    <x-page-header title="Announcements" icon="bi-broadcast" subtitle="Company announcements sent to leads and customers.">
        @can('create', App\Models\Announcement::class)
            <x-slot:actions>
                <a href="{{ route('announcements.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> New Announcement
                </a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Audience</th>
                        @can('viewDelivery', App\Models\Announcement::class)
                            <th class="text-center">Recipients</th>
                            <th>Delivery</th>
                        @endcan
                        <th>Sent By</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php $canViewDelivery = auth()->user()->can('viewDelivery', App\Models\Announcement::class); @endphp
                    @forelse ($announcements as $announcement)
                        <tr>
                            <td class="small fw-semibold">
                                <a href="{{ route('announcements.show', $announcement) }}" class="text-decoration-none">{{ $announcement->title }}</a>
                            </td>
                            <td class="small">{{ $announcement->audience->label() }}</td>
                            @if ($canViewDelivery)
                                <td class="small text-center">{{ $announcement->recipient_count }}</td>
                                <td class="small">
                                    <span class="badge bg-success-subtle text-success-emphasis">{{ $announcement->sent_count }} sent</span>
                                    @if ($announcement->pending_count)
                                        <span class="badge bg-warning-subtle text-warning-emphasis">{{ $announcement->pending_count }} pending</span>
                                    @endif
                                    @if ($announcement->failed_count)
                                        <span class="badge bg-danger-subtle text-danger-emphasis">{{ $announcement->failed_count }} failed</span>
                                    @endif
                                </td>
                            @endif
                            <td class="small text-muted">{{ $announcement->creator?->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $announcement->created_at->format('M d, Y g:i A') }}</td>
                            <td class="text-end">
                                <a href="{{ route('announcements.show', $announcement) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canViewDelivery ? 7 : 5 }}">
                                <x-empty-state icon="bi-broadcast" title="No announcements yet" description="Announcements from the team will appear here." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($announcements->hasPages())
            <div class="card-footer bg-white">
                {{ $announcements->links() }}
            </div>
        @endif
    </div>
@endsection
