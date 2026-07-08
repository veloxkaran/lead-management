<form method="POST" action="{{ route('leads.notes.store', $lead) }}" enctype="multipart/form-data" class="mb-3">
    @csrf
    <textarea name="comment" rows="2" class="form-control form-control-sm mb-2" placeholder="Add a note..." required></textarea>
    <div class="d-flex justify-content-between align-items-center">
        <input type="file" name="attachments[]" multiple class="form-control form-control-sm w-auto">
        <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add Note</button>
    </div>
</form>

@forelse ($lead->notes as $note)
    <div class="border rounded-3 p-2 mb-2">
        <div class="d-flex justify-content-between">
            <span class="fw-semibold small">{{ $note->author?->name }}</span>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">{{ $note->created_at->diffForHumans() }}</span>
                @can('delete', $note)
                    <form method="POST" action="{{ route('leads.notes.destroy', [$lead, $note]) }}" data-confirm-delete data-confirm-title="Delete this note?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                    </form>
                @endcan
            </div>
        </div>
        <p class="small mb-1">{{ $note->comment }}</p>
        @foreach ($note->attachments as $attachment)
            <a href="{{ route('lead-note-attachments.download', $attachment) }}" class="badge bg-light text-dark border text-decoration-none me-1">
                <i class="bi bi-paperclip"></i> {{ $attachment->original_name }}
            </a>
        @endforeach
    </div>
@empty
    <x-empty-state icon="bi-chat-left-text" title="No notes yet" />
@endforelse
