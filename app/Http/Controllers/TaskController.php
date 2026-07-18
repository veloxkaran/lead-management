<?php

namespace App\Http\Controllers;

use App\Enums\ActivityModule;
use App\Enums\TaskModule;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\OrganizationHierarchyService;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(protected TaskService $tasks, protected OrganizationHierarchyService $hierarchy)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Task::class);

        $filters = $request->only(['status', 'priority', 'module', 'lead_id']);

        return view('tasks.index', [
            'tasks' => $this->tasks->list($filters, $request->user(), 20),
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'modules' => TaskModule::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        return view('tasks.create', [
            'leads' => Lead::active()->orderBy('company_name')->get(),
            'modules' => TaskModule::cases(),
            'priorities' => TaskPriority::cases(),
            'users' => $this->assignableUsers($request->user()),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = $this->tasks->create($request->validated(), $request->user());

        return redirect()->route('tasks.show', $task)->with('success', 'Task created successfully.');
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);

        $task->load([
            'assignee', 'assignedBy', 'creator', 'lead', 'taskable',
            'checklistItems.completedBy', 'comments.author',
        ]);

        $activity = ActivityLogEntry::where('module', ActivityModule::Task)
            ->where('subject_type', $task->getMorphClass())
            ->where('subject_id', $task->id)
            ->with('user')
            ->latest()
            ->get();

        return view('tasks.show', [
            'task' => $task,
            'activity' => $activity,
        ]);
    }

    public function edit(Request $request, Task $task): View
    {
        $this->authorize('update', $task);

        $task->load('lead', 'assignee', 'assignedBy');

        return view('tasks.edit', [
            'task' => $task,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'users' => $this->assignableUsers($request->user()),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->update($task, $request->validated(), $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('tasks.show', $task)->with('success', 'Task updated successfully.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $this->tasks->delete($task, $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('tasks.index')->with('success', 'Task deleted successfully.');
    }

    private function assignableUsers(User $viewer)
    {
        if ($viewer->isOverseer()) {
            return User::orderBy('name')->get();
        }

        $ids = $this->hierarchy->getAllSubordinateIds($viewer)->push($viewer->id);

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }
}
