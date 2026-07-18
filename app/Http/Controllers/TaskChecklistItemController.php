<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\StoreTaskChecklistItemRequest;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskChecklistItemController extends Controller
{
    public function __construct(protected TaskService $tasks)
    {
    }

    public function store(StoreTaskChecklistItemRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->addChecklistItem($task, $request->validated(), $request->user());

        return back()->with('success', 'Checklist item added.');
    }

    public function update(Request $request, Task $task, TaskChecklistItem $checklistItem): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->tasks->toggleChecklistItem($checklistItem, $request->user());

        return back()->with('success', 'Checklist item updated.');
    }

    public function destroy(Task $task, TaskChecklistItem $checklistItem): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->tasks->removeChecklistItem($checklistItem);

        return back()->with('success', 'Checklist item removed.');
    }
}
