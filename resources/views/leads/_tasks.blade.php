<form method="POST" action="{{ route('leads.tasks.store', $lead) }}" class="row g-2 mb-3">
    @csrf
    <input type="hidden" name="module" value="lead">
    <div class="col-md-4">
        <input type="text" name="title" class="form-control form-control-sm" placeholder="Task title" required>
    </div>
    <div class="col-md-3">
        <select name="assigned_to" class="form-select form-select-sm">
            <option value="">Unassigned</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="due_date" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
        <select name="priority" class="form-select form-select-sm">
            @foreach ($taskPriorities as $priority)
                <option value="{{ $priority->value }}" @selected($priority->value === 'medium')>{{ $priority->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-1">
        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Title</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Due</th><th></th></tr></thead>
        <tbody>
            @forelse ($lead->tasks as $task)
                <tr>
                    <td class="small"><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                    <td><x-status-badge :status="$task->priority" /></td>
                    <td><x-status-badge :status="$task->status" /></td>
                    <td class="small">{{ $task->assignee?->name ?? '—' }}</td>
                    <td class="small">{{ $task->due_date?->format('M d, Y') ?? '—' }}</td>
                    <td>
                        @can('update', $task)
                            <a href="{{ route('tasks.edit', $task) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="bi-list-task" title="No tasks yet" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
