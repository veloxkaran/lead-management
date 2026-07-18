<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\StoreTaskCommentRequest;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    public function __construct(protected TaskService $tasks)
    {
    }

    public function store(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->addComment($task, $request->validated(), $request->user());

        return back()->with('success', 'Comment added.');
    }

    public function destroy(Task $task, TaskComment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $this->tasks->deleteComment($comment);

        return back()->with('success', 'Comment deleted.');
    }
}
