<?php

namespace App\Http\Controllers;

use App\Enums\AgendaStatus;
use App\Http\Requests\Agenda\StoreAgendaCommentRequest;
use App\Http\Requests\Agenda\StoreAgendaRequest;
use App\Http\Requests\Agenda\UpdateAgendaStatusRequest;
use App\Models\Agenda;
use App\Models\AgendaComment;
use App\Services\AgendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Team Meeting Room, mirroring WhatsappChatController's single
 * list+detail view keyed by a selected id, rather than a separate
 * AgendaController resource — this is one cohesive shared workspace, not a
 * CRUD resource with per-record pages.
 */
class MeetingRoomController extends Controller
{
    public function __construct(protected AgendaService $agendas)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Agenda::class);

        $filters = $request->only(['search', 'status', 'sort']);

        $activeAgenda = null;

        if ($request->filled('agenda')) {
            $activeAgenda = Agenda::with(['creator', 'lastUpdatedBy', 'finalizedBy'])
                ->withCount('comments')
                ->find($request->integer('agenda'));
        }

        return view('meeting-room.index', [
            'agendas' => $this->agendas->list($filters, $request->user()),
            'activeAgenda' => $activeAgenda,
            'initialComments' => $activeAgenda ? $this->threadFor($activeAgenda) : [],
            'statuses' => AgendaStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreAgendaRequest $request): RedirectResponse
    {
        $agenda = $this->agendas->create($request->validated(), $request->user());

        return redirect()->route('meeting-room.index', ['agenda' => $agenda->id])
            ->with('success', 'Agenda raised.');
    }

    public function updateStatus(UpdateAgendaStatusRequest $request, Agenda $agenda): JsonResponse
    {
        $this->agendas->changeStatus($agenda, AgendaStatus::from($request->validated('status')), $request->user());

        return response()->json([
            'status' => $agenda->status->value,
            'status_label' => $agenda->status->label(),
            'finalized_by' => $agenda->finalizedBy?->name,
            'finalized_at' => $agenda->finalized_at?->format('M d, Y g:i A'),
        ]);
    }

    /**
     * Cursor-append poll endpoint, same shape as
     * WhatsappChatController::messages() — returns only comments/replies
     * created after the client's last-seen id.
     */
    public function discussions(Request $request, Agenda $agenda): JsonResponse
    {
        $this->authorize('view', $agenda);

        $validated = $request->validate(['after_id' => ['nullable', 'integer']]);

        $comments = $agenda->comments()
            ->with('author')
            ->when($validated['after_id'] ?? null, fn ($q, $afterId) => $q->where('id', '>', $afterId))
            ->get();

        return response()->json([
            'comments' => $comments->map(fn (AgendaComment $comment) => $this->serializeComment($comment)),
        ]);
    }

    public function storeComment(StoreAgendaCommentRequest $request, Agenda $agenda): JsonResponse
    {
        $comment = $this->agendas->addComment($agenda, $request->validated(), $request->user());

        return response()->json(['comment' => $this->serializeComment($comment)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function threadFor(Agenda $agenda): array
    {
        return $agenda->comments()->with('author')->get()
            ->map(fn (AgendaComment $comment) => $this->serializeComment($comment))
            ->all();
    }

    private function serializeComment(AgendaComment $comment): array
    {
        return [
            'id' => $comment->id,
            'agenda_id' => $comment->agenda_id,
            'parent_id' => $comment->parent_id,
            'author_id' => $comment->author_id,
            'author_name' => $comment->author->name,
            'comment' => $comment->comment,
            'formatted_at' => $comment->created_at->format('M d, Y g:i A'),
        ];
    }
}
