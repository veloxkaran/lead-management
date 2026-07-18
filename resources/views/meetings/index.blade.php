@extends('layouts.app')

@section('title', 'Meetings')

@section('content')
    <x-page-header title="Meetings" icon="bi-camera-video" subtitle="Google Meet-style meeting scheduling.">
        <x-slot:actions>
            <a href="{{ route('meetings.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Schedule Meeting</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('meetings.index', ['scope' => 'mine']) }}" class="btn btn-outline-primary {{ $scope === 'mine' ? 'active' : '' }}">My Meetings</a>
                @if (auth()->user()->isOverseer())
                    <a href="{{ route('meetings.index', ['scope' => 'all']) }}" class="btn btn-outline-primary {{ $scope === 'all' ? 'active' : '' }}">All Meetings</a>
                @endif
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Participants</th>
                        <th>Link</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($meetings as $meeting)
                        <tr>
                            <td class="fw-semibold">{{ $meeting->title }}</td>
                            <td>{{ $meeting->meeting_date->format('M d, Y') }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($meeting->meeting_time)->format('g:i A') }}</td>
                            <td class="small text-muted">
                                @php $participants = $meeting->participants ?? []; @endphp
                                @if (count($participants) === 0)
                                    &mdash;
                                @elseif (count($participants) <= 3)
                                    {{ implode(', ', $participants) }}
                                @else
                                    {{ count($participants) }} participants
                                @endif
                            </td>
                            <td>
                                <a href="{{ $meeting->meeting_link }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-camera-video"></i> Join</a>
                            </td>
                            <td class="text-end">
                                @can('update', $meeting)
                                    <a href="{{ route('meetings.edit', $meeting) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $meeting)
                                    <form action="{{ route('meetings.destroy', $meeting) }}" method="POST" class="d-inline" data-confirm-delete data-confirm-title="Delete meeting?" data-confirm-text="This cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state icon="bi-camera-video" title="No meetings found" description="Schedule a meeting to get started." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($meetings->hasPages())
            <div class="card-footer bg-white">
                {{ $meetings->links() }}
            </div>
        @endif
    </div>
@endsection
