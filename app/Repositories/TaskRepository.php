<?php

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskRepository extends BaseRepository
{
    public function __construct(Task $model)
    {
        parent::__construct($model);
    }

    /**
     * Hierarchy scoping happens in the query itself, not just in
     * TaskPolicy::view() per-record — a manager's task list never loads
     * rows outside their reporting chain in the first place. Scoped by
     * assigned_to OR created_by (both checked against the same
     * $visibleUserIds set) so unassigned/pending tasks a visible user
     * created are still reachable, without widening visibility beyond the
     * hierarchy.
     */
    public function filter(array $filters, Collection $visibleUserIds, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['assignee', 'assignedBy', 'lead', 'taskable']);

        $query->where(function ($q) use ($visibleUserIds) {
            $q->whereIn('assigned_to', $visibleUserIds)
                ->orWhereIn('created_by', $visibleUserIds);
        });

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
