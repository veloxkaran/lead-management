@extends('layouts.app')

@section('title', 'Training History — '.$lead->company_name)

@section('content')
    <x-page-header title="Training History" icon="bi-mortarboard" :subtitle="$lead->company_name">
        <x-slot:actions>
            <a href="{{ route('leads.show', $lead) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Lead</a>
            @can('create', App\Models\Training::class)
                <a href="{{ route('trainings.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Schedule Training</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Training Date</th>
                        <th>Trainer</th>
                        <th>Attendees</th>
                        <th>Department</th>
                        <th>Completion</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trainings as $training)
                        <tr>
                            <td class="small">{{ $training->training_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="small text-muted">{{ $training->trainer_name ?? '—' }}</td>
                            <td class="small">{{ $training->attendees_count ?? '—' }}</td>
                            <td class="small">{{ $training->department?->name ?? '—' }}</td>
                            <td class="small">{{ $training->completion_percentage }}%</td>
                            <td><x-status-badge :status="$training->status" /></td>
                            <td class="text-end">
                                @can('update', $training)
                                    <a href="{{ route('trainings.edit', $training) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state icon="bi-mortarboard" title="No training history yet" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($trainings->hasPages())
            <div class="card-footer bg-white">
                {{ $trainings->links() }}
            </div>
        @endif
    </div>
@endsection
