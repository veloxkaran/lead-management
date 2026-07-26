<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-chat-left-text"></i> Comments ({{ $supportTicket->comments->count() }})
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('support-tickets.comments.store', $supportTicket) }}" class="mb-3">
            @csrf
            <textarea name="comment" rows="2" class="form-control form-control-sm" placeholder="Add a comment..." required></textarea>
            <button type="submit" class="btn btn-sm btn-primary mt-2"><i class="bi bi-send"></i> Post Comment</button>
        </form>

        @forelse ($supportTicket->comments as $comment)
            <div class="border-bottom pb-2 mb-2">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <span class="fw-semibold small">{{ $comment->author?->name ?? 'Unknown' }}</span>
                        <span class="text-muted small">{{ $comment->created_at->format('M d, Y g:i A') }}</span>
                        @if ($comment->wasEdited())
                            <span class="text-muted small fst-italic">(edited)</span>
                        @endif
                    </div>
                    @if ($comment->author_id === auth()->id() && $comment->isEditable())
                        <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#editComment{{ $comment->id }}">
                            Edit
                        </button>
                    @endif
                </div>
                <p class="small mb-1">{{ $comment->comment }}</p>

                @if ($comment->author_id === auth()->id() && $comment->isEditable())
                    <div class="collapse" id="editComment{{ $comment->id }}">
                        <form method="POST" action="{{ route('support-tickets.comments.update', [$supportTicket, $comment]) }}" class="d-flex gap-2">
                            @csrf
                            @method('PATCH')
                            <textarea name="comment" rows="2" class="form-control form-control-sm">{{ $comment->comment }}</textarea>
                            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Save</button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-muted small mb-0">No comments yet.</p>
        @endforelse
    </div>
</div>
