<?php

namespace App\Services;

use App\Enums\ActivityModule;
use App\Enums\TaskModule;
use App\Enums\TaskStatus;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskService
{
    public function __construct(
        protected TaskRepository $tasks,
        protected OrganizationHierarchyService $hierarchy,
    ) {
    }

    public function list(array $filters, User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        return $this->tasks->filter($filters, $this->hierarchy->visibleUserIds($viewer), $perPage);
    }

    public function create(array $attributes, User $actor): Task
    {
        $attributes['created_by'] = $actor->id;

        if (! empty($attributes['assigned_to']) && empty($attributes['assigned_by'])) {
            $attributes['assigned_by'] = $actor->id;
        }

        if (empty($attributes['status'])) {
            $attributes['status'] = (! empty($attributes['assigned_to']))
                ? TaskStatus::Assigned->value
                : TaskStatus::Pending->value;
        }

        $attributes['lead_id'] = $attributes['lead_id'] ?? $this->resolveLeadId($attributes);

        /** @var Task $task */
        $task = $this->tasks->create($attributes);

        return $task;
    }

    public function createForLead(Lead $lead, array $attributes, User $actor): Task
    {
        $attributes['lead_id'] = $lead->id;
        $attributes['module'] = $attributes['module'] ?? TaskModule::Lead->value;

        return $this->create($attributes, $actor);
    }

    public function update(Task $task, array $attributes, User $actor, ?string $ip, ?string $userAgent): Task
    {
        if (($attributes['status'] ?? null) === TaskStatus::Completed->value && ! $task->completed_at) {
            $attributes['completed_at'] = now();
        }

        if (! empty($attributes['assigned_to']) && $attributes['assigned_to'] !== $task->assigned_to && empty($attributes['assigned_by'])) {
            $attributes['assigned_by'] = $actor->id;
        }

        // getDirty() (captured after fill(), before save()) rather than
        // getChanges() (captured after save()) — this doesn't depend on
        // whether anything downstream (e.g. a repository refresh()) touches
        // the model afterward, so the diff is correct regardless.
        // getRawOriginal() (not only()/the cast accessor) keeps old/new
        // symmetric — both are raw storage values, not a mix of enum/Carbon
        // objects and raw strings.
        $originalRaw = collect(array_keys($attributes))
            ->mapWithKeys(fn ($key) => [$key => $task->getRawOriginal($key)])
            ->all();

        $task->fill($attributes);
        $changed = $task->getDirty();
        unset($changed['updated_at']);

        $task->save();

        if (! empty($changed)) {
            $this->logChange($task, $actor, $ip, $userAgent, array_intersect_key($originalRaw, $changed), $changed);
        }

        return $task;
    }

    public function delete(Task $task, User $actor, ?string $ip, ?string $userAgent): void
    {
        $this->logActivity($task, $actor, $ip, $userAgent, "deleted task \"{$task->title}\"");

        $this->tasks->delete($task);
    }

    public function addChecklistItem(Task $task, array $attributes, User $actor): TaskChecklistItem
    {
        return $task->checklistItems()->create([
            ...$attributes,
            'created_by' => $actor->id,
            'position' => $task->checklistItems()->max('position') + 1,
        ]);
    }

    public function toggleChecklistItem(TaskChecklistItem $item, User $actor): TaskChecklistItem
    {
        $item->update([
            'is_completed' => ! $item->is_completed,
            'completed_at' => ! $item->is_completed ? now() : null,
            'completed_by' => ! $item->is_completed ? $actor->id : null,
        ]);

        return $item;
    }

    public function removeChecklistItem(TaskChecklistItem $item): void
    {
        $item->delete();
    }

    public function addComment(Task $task, array $attributes, User $actor): TaskComment
    {
        return $task->comments()->create([
            ...$attributes,
            'author_id' => $actor->id,
        ]);
    }

    public function deleteComment(TaskComment $comment): void
    {
        $comment->delete();
    }

    private function resolveLeadId(array $attributes): ?int
    {
        if (empty($attributes['taskable_type']) || empty($attributes['taskable_id'])) {
            return null;
        }

        $taskable = $attributes['taskable_type']::find($attributes['taskable_id']);

        return match (true) {
            $taskable instanceof \App\Models\Lead => $taskable->id,
            $taskable && isset($taskable->lead_id) => $taskable->lead_id,
            default => null,
        };
    }

    private function logChange(Task $task, User $actor, ?string $ip, ?string $userAgent, array $oldValues, array $newValues): void
    {
        ActivityLogEntry::create([
            'company_id' => $task->company_id ?? $actor->company_id,
            'user_id' => $actor->id,
            'module' => ActivityModule::Task,
            'description' => "updated task \"{$task->title}\"",
            'subject_type' => $task->getMorphClass(),
            'subject_id' => $task->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    private function logActivity(Task $task, User $actor, ?string $ip, ?string $userAgent, string $description): void
    {
        ActivityLogEntry::create([
            'company_id' => $task->company_id ?? $actor->company_id,
            'user_id' => $actor->id,
            'module' => ActivityModule::Task,
            'description' => $description,
            'subject_type' => $task->getMorphClass(),
            'subject_id' => $task->getKey(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
