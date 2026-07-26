@extends('layouts.app')

@section('title', 'Team Meeting Room')

@section('content')
    <x-page-header title="Team Meeting Room" icon="bi-people" subtitle="Raise agendas, discuss them, and track their progress with the whole team.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAgendaModal">
                <i class="bi bi-plus-lg"></i> New Agenda
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3" style="min-height: 75vh;">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                <div class="card-header bg-white">
                    <form method="GET" action="{{ route('meeting-room.index') }}" id="agendaFilterForm">
                        @if ($activeAgenda)
                            <input type="hidden" name="agenda" value="{{ $activeAgenda->id }}">
                        @endif
                        <input
                            type="search"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            class="form-control form-control-sm mb-2"
                            placeholder="Search title, description, or creator..."
                            x-data
                            x-on:input.debounce.500ms="$el.form.requestSubmit()"
                        >
                        <div class="d-flex gap-2">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All Agendas</option>
                                <option value="mine" @selected(($filters['status'] ?? '') === 'mine')>Created By Me</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest First</option>
                                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest First</option>
                                <option value="recently_updated" @selected(($filters['sort'] ?? '') === 'recently_updated')>Recently Updated</option>
                                <option value="most_discussed" @selected(($filters['sort'] ?? '') === 'most_discussed')>Most Discussed</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="list-group list-group-flush overflow-auto flex-grow-1">
                    @forelse ($agendas as $agenda)
                        <a href="{{ request()->fullUrlWithQuery(['agenda' => $agenda->id]) }}"
                           class="list-group-item list-group-item-action {{ $activeAgenda?->id === $agenda->id ? 'active' : '' }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="fw-semibold small">{{ $agenda->title }}</span>
                                <x-status-badge :status="$agenda->status" />
                            </div>
                            <div class="small text-muted">{{ $agenda->creator?->name ?? 'Unknown' }} &middot; {{ $agenda->created_at->format('M d, Y') }}</div>
                            <div class="small text-muted"><i class="bi bi-chat-left-text"></i> {{ $agenda->comments_count }} {{ Str::plural('discussion', $agenda->comments_count) }}</div>
                        </a>
                    @empty
                        <x-empty-state icon="bi-people" title="No agendas yet" description="Raise the first agenda for the team to discuss." />
                    @endforelse
                </div>
                @if ($agendas->hasPages())
                    <div class="card-footer bg-white">
                        {{ $agendas->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                @if ($activeAgenda)
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h5 class="mb-1">{{ $activeAgenda->title }}</h5>
                            <x-status-badge :status="$activeAgenda->status" />
                        </div>
                        <div class="small text-muted">
                            Created by {{ $activeAgenda->creator?->name ?? 'Unknown' }} on {{ $activeAgenda->created_at->format('M d, Y g:i A') }}
                            @if ($activeAgenda->lastUpdatedBy)
                                &middot; Last updated by {{ $activeAgenda->lastUpdatedBy->name }} on {{ $activeAgenda->updated_at->format('M d, Y g:i A') }}
                            @endif
                        </div>
                        @if ($activeAgenda->status->isFinalized())
                            <div class="small text-muted">
                                {{ $activeAgenda->status->label() }} by {{ $activeAgenda->finalizedBy?->name ?? 'Unknown' }} on {{ $activeAgenda->finalized_at?->format('M d, Y g:i A') }}
                            </div>
                        @endif
                    </div>

                    @if ($activeAgenda->description)
                        <div class="card-body py-2 border-bottom">
                            <p class="small mb-0">{{ $activeAgenda->description }}</p>
                        </div>
                    @endif

                    @if ($activeAgenda->created_by === auth()->id() && $activeAgenda->isPending())
                        <div class="card-body py-2 border-bottom d-flex gap-2">
                            <form method="POST" action="{{ route('meeting-room.status.update', $activeAgenda) }}"
                                  data-confirm-delete
                                  data-confirm-title="Close this agenda?"
                                  data-confirm-text="Once closed, this agenda can never be reopened."
                                  data-confirm-button-text="Yes, close it">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="closed">
                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Close</button>
                            </form>
                            <form method="POST" action="{{ route('meeting-room.status.update', $activeAgenda) }}"
                                  data-confirm-delete
                                  data-confirm-title="Dismiss this agenda?"
                                  data-confirm-text="Once dismissed, this agenda can never be reopened."
                                  data-confirm-button-text="Yes, dismiss it">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="dismissed">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i> Dismiss</button>
                            </form>
                        </div>
                    @endif

                    <div class="card-body overflow-auto flex-grow-1"
                         x-data="agendaThread({{ $activeAgenda->id }}, @json($initialComments), {{ $activeAgenda->isPending() ? 'true' : 'false' }})">
                        <template x-for="comment in topLevel()" :key="comment.id">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-baseline gap-2">
                                    <span class="fw-semibold small" x-text="comment.author_name"></span>
                                    <span class="text-muted" style="font-size: 0.72rem;" x-text="comment.formatted_at"></span>
                                </div>
                                <div class="small" x-text="comment.comment"></div>
                                <button type="button" class="btn btn-link btn-sm p-0" x-show="pending" @click="toggleReply(comment.id)">Reply</button>

                                <template x-for="reply in repliesFor(comment.id)" :key="reply.id">
                                    <div class="ms-4 mt-2 border-start ps-2">
                                        <div class="d-flex justify-content-between align-items-baseline gap-2">
                                            <span class="fw-semibold small" x-text="reply.author_name"></span>
                                            <span class="text-muted" style="font-size: 0.7rem;" x-text="reply.formatted_at"></span>
                                        </div>
                                        <div class="small" x-text="reply.comment"></div>
                                    </div>
                                </template>

                                <template x-if="pending && replyOpen[comment.id]">
                                    <form class="ms-4 mt-2 d-flex gap-2" @submit.prevent="postComment(comment.id)">
                                        <input type="text" class="form-control form-control-sm" x-model="replyBody[comment.id]" placeholder="Write a reply...">
                                        <button class="btn btn-sm btn-outline-primary text-nowrap" type="submit" :disabled="posting">Reply</button>
                                    </form>
                                </template>
                            </div>
                        </template>
                        <template x-if="comments.length === 0">
                            <div class="text-muted small text-center py-4">No discussion yet. Be the first to comment.</div>
                        </template>

                        <div x-show="pending" class="border-top pt-3 mt-2">
                            <form @submit.prevent="postComment(null)" class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" x-model="newComment" placeholder="Add a comment... (use @name to mention someone)" required>
                                <button class="btn btn-sm btn-primary text-nowrap" type="submit" :disabled="posting"><i class="bi bi-send"></i></button>
                            </form>
                        </div>
                        <div x-show="!pending" class="border-top pt-3 mt-2 text-muted small">
                            This agenda is finalized — the discussion is preserved for reference but closed to new comments.
                        </div>
                    </div>
                @else
                    <x-empty-state icon="bi-people" title="Select an agenda" description="Pick an agenda on the left, or raise a new one." />
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="newAgendaModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('meeting-room.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">New Agenda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" rows="3" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Raise Agenda</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function agendaThread(agendaId, initialComments, pending) {
            return {
                agendaId,
                comments: initialComments,
                pending,
                newComment: '',
                replyOpen: {},
                replyBody: {},
                posting: false,
                pollTimer: null,

                init() {
                    this.pollTimer = setInterval(() => this.poll(), 5000);
                },

                topLevel() {
                    return this.comments.filter((c) => !c.parent_id);
                },

                repliesFor(parentId) {
                    return this.comments.filter((c) => c.parent_id === parentId);
                },

                toggleReply(id) {
                    this.replyOpen[id] = !this.replyOpen[id];
                },

                poll() {
                    const afterId = this.comments.length ? Math.max(...this.comments.map((c) => c.id)) : null;

                    axios.get(`/meeting-room/${this.agendaId}/discussions`, { params: { after_id: afterId } })
                        .then(({ data }) => {
                            if (data.comments.length) {
                                this.comments.push(...data.comments);
                            }
                        });
                },

                postComment(parentId) {
                    const body = parentId ? this.replyBody[parentId] : this.newComment;

                    if (!body) {
                        return;
                    }

                    this.posting = true;

                    axios.post(`/meeting-room/${this.agendaId}/discussions`, { comment: body, parent_id: parentId })
                        .then(({ data }) => {
                            this.comments.push(data.comment);

                            if (parentId) {
                                this.replyBody[parentId] = '';
                                this.replyOpen[parentId] = false;
                            } else {
                                this.newComment = '';
                            }
                        })
                        .catch((error) => {
                            Swal.fire('Could not post comment', error.response?.data?.message ?? 'Please try again.', 'error');
                        })
                        .finally(() => {
                            this.posting = false;
                        });
                },
            };
        }
    </script>
@endpush
