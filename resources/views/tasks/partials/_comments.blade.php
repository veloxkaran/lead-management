@can('view', $task)
    <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="mb-3">
        @csrf
        <textarea name="comment" rows="2" class="form-control form-control-sm mb-2" placeholder="Add a comment" required></textarea>
        @error('comment')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
        <button class="btn btn-sm btn-primary"><i class="bi bi-chat-left-text"></i> Post Comment</button>
    </form>
@endcan

@forelse ($task->comments as $comment)
    <div class="border-bottom pb-2 mb-2">
        <div class="d-flex justify-content-between">
            <strong class="small">{{ $comment->author?->name ?? 'Unknown' }}</strong>
            <span class="text-muted small">{{ $comment->created_at->diffForHumans() }}</span>
        </div>
        <p class="small mb-1">{{ $comment->comment }}</p>
        @can('delete', $comment)
            <form method="POST" action="{{ route('tasks.comments.destroy', [$task, $comment]) }}">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-link text-danger p-0 small">Delete</button>
            </form>
        @endcan
    </div>
@empty
    <x-empty-state icon="bi-chat-left-text" title="No comments yet" />
@endforelse
