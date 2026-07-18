@extends('layouts.app')

@section('title', 'Tasks')

@section('content')
    <x-page-header title="Tasks" icon="bi-list-task" subtitle="Organization-wide task tracking, scoped to your reporting hierarchy.">
        <x-slot:actions>
            @can('create', App\Models\Task::class)
                <a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> New Task
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" data-select2>
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Priority</label>
                    <select name="priority" class="form-select form-select-sm" data-select2>
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Module</label>
                    <select name="module" class="form-select form-select-sm" data-select2>
                        <option value="">All modules</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module->value }}" @selected(($filters['module'] ?? null) === $module->value)>{{ $module->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="{{ route('tasks.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Module</th>
                        <th>Client</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $task)
                        <tr class="@if ($task->isOverdue()) table-danger @endif">
                            <td>
                                <a href="{{ route('tasks.show', $task) }}" class="text-decoration-none fw-semibold">{{ $task->title }}</a>
                            </td>
                            <td class="small">{{ $task->module->label() }}</td>
                            <td class="small">
                                @if ($task->lead)
                                    <a href="{{ route('leads.show', $task->lead) }}" class="text-decoration-none">{{ $task->lead->company_name }}</a>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="small">{{ $task->assignee?->name ?? '—' }}</td>
                            <td class="small">
                                {{ $task->due_date?->format('M d, Y') ?? '—' }}
                                @if ($task->isOverdue())
                                    <span class="badge bg-danger ms-1">Overdue</span>
                                @endif
                            </td>
                            <td><x-status-badge :status="$task->priority" /></td>
                            <td><x-status-badge :status="$task->status" /></td>
                            <td class="text-end">
                                @can('update', $task)
                                    <a href="{{ route('tasks.edit', $task) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('delete', $task)
                                    <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="d-inline" data-confirm-delete data-confirm-title="Delete this task?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="bi-list-task" title="No tasks found" description="Try adjusting your filters or create a new task." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tasks->hasPages())
            <div class="card-footer bg-white">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
@endsection
