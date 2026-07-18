@extends('layouts.app')

@section('title', 'Trainings')

@section('content')
    <x-page-header title="Trainings" icon="bi-mortarboard" subtitle="Client onboarding and training sessions.">
        <x-slot:actions>
            @can('create', App\Models\Training::class)
                <a href="{{ route('trainings.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Schedule Training
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('trainings.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Client</th>
                        <th>Training Date</th>
                        <th>Trainer</th>
                        <th>Attendees</th>
                        <th>Completion</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trainings as $training)
                        <tr>
                            <td>
                                @if ($training->lead)
                                    <a href="{{ route('leads.show', $training->lead) }}" class="text-decoration-none fw-semibold">{{ $training->lead->company_name }}</a>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="small">{{ $training->training_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="small text-muted">{{ $training->trainer_name ?? '—' }}</td>
                            <td class="small">{{ $training->attendees_count ?? '—' }}</td>
                            <td class="small">{{ $training->completion_percentage }}%</td>
                            <td><x-status-badge :status="$training->status" /></td>
                            <td class="text-end">
                                @can('update', $training)
                                    <a href="{{ route('trainings.edit', $training) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $training)
                                    <form method="POST" action="{{ route('trainings.destroy', $training) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this training record?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state icon="bi-mortarboard" title="No trainings found" description="Try adjusting your filters or schedule a new training." />
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
