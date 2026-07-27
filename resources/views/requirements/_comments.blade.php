<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-chat-left-text"></i> Comments ({{ $requirement->comments->count() }})
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('requirements.comments.store', $requirement) }}" class="mb-3">
            @csrf
            <textarea name="comment" rows="2" class="form-control form-control-sm" placeholder="Add a comment..." required></textarea>
            <button type="submit" class="btn btn-sm btn-primary mt-2"><i class="bi bi-send"></i> Post Comment</button>
        </form>

        @forelse ($requirement->comments as $comment)
            <div class="border-bottom pb-2 mb-2">
                <div>
                    <span class="fw-semibold small">{{ $comment->author?->name ?? 'Unknown' }}</span>
                    <span class="text-muted small">{{ $comment->created_at->format('M d, Y g:i A') }}</span>
                </div>
                <p class="small mb-0">{{ $comment->comment }}</p>
            </div>
        @empty
            <p class="text-muted small mb-0">No comments yet.</p>
        @endforelse
    </div>
</div>
