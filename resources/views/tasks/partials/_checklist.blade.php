@can('update', $task)
    <form method="POST" action="{{ route('tasks.checklist-items.store', $task) }}" class="d-flex gap-2 mb-3">
        @csrf
        <input type="text" name="title" class="form-control form-control-sm" placeholder="Add a checklist item" required>
        <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Add</button>
    </form>
    @error('title')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
@endcan

@forelse ($task->checklistItems as $item)
    <div class="d-flex align-items-center gap-2 mb-2">
        @can('update', $task)
            <form method="POST" action="{{ route('tasks.checklist-items.update', [$task, $item]) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-sm {{ $item->is_completed ? 'btn-success' : 'btn-outline-secondary' }}">
                    <i class="bi {{ $item->is_completed ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                </button>
            </form>
        @else
            <i class="bi {{ $item->is_completed ? 'bi-check-circle-fill text-success' : 'bi-circle' }}"></i>
        @endcan
        <span class="flex-fill small @if ($item->is_completed) text-decoration-line-through text-muted @endif">{{ $item->title }}</span>
        @if ($item->is_completed && $item->completedBy)
            <span class="text-muted small">{{ $item->completedBy->name }}</span>
        @endif
        @can('update', $task)
            <form method="POST" action="{{ route('tasks.checklist-items.destroy', [$task, $item]) }}">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        @endcan
    </div>
@empty
    <x-empty-state icon="bi-check2-square" title="No checklist items yet" />
@endforelse
