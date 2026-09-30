<?php

namespace App\Repositories;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class RequirementRepository extends BaseRepository
{
    public function __construct(Requirement $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Every requirement matching $filters, with only the columns
     * RequirementSummary needs, for the counts strip on the Requirements list.
     */
    public function forSummary(array $filters = []): Collection
    {
        return $this->applyFilters($this->query(), $filters)
            ->get(['id', 'status', 'due_date', 'created_at', 'completed_at']);
    }

    /**
     * Every requirement belonging to one company, for that company's
     * dedicated requirement-list page.
     */
    public function forLead(Lead $lead): Collection
    {
        return $this->orderedForDisplay($lead->requirements()->with('attachments'))->get();
    }

    private function orderedForDisplay($query)
    {
        return $this->applyDisplayOrder($query->with(['systemModule', 'assignee', 'creator'])->withCount('comments'));
    }

    /**
     * Unpaginated — the PDF export needs every matching row, not just the
     * current page, so it shares the same where-clauses as filter() rather
     * than duplicating them. Named distinctly from BaseRepository::all()
     * (which takes eager-load relations, not filters) to avoid a
     * confusingly-different override of the same method name.
     */
    public function allFiltered(array $filters = []): Collection
    {
        return $this->filteredQuery($filters)->get();
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = $this->query()->with(['lead', 'systemModule', 'assignee', 'creator', 'attachments'])->withCount('comments');

        return $this->applyDisplayOrder($this->applyFilters($query, $filters));
    }

    /**
     * Same order as the Support Tickets list: by status (open work first,
     * completed last), then priority (urgent first), then newest first.
     */
    private function applyDisplayOrder($query)
    {
        return $query
            ->orderByRaw(
                'CASE status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 WHEN ? THEN 5 ELSE 6 END',
                [
                    RequirementStatus::Pending->value,
                    RequirementStatus::InProgress->value,
                    RequirementStatus::InReview->value,
                    RequirementStatus::OnHold->value,
                    RequirementStatus::Completed->value,
                ]
            )
            ->orderByRaw(
                'CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END',
                [
                    RequirementPriority::Urgent->value,
                    RequirementPriority::High->value,
                    RequirementPriority::Medium->value,
                    RequirementPriority::Low->value,
                ]
            )
            ->latest();
    }

    /**
     * Where-clauses only, shared by the list/PDF query and the summary
     * counts so both always describe the same set of requirements.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('requirement', 'like', $term)
                    ->orWhereHas('lead', fn ($lead) => $lead->where('company_name', 'like', $term));
            });
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        // "_none" picks out requirements logged before modules existed.
        if (($filters['system_module_id'] ?? null) === '_none') {
            $query->whereNull('system_module_id');
        } elseif (! empty($filters['system_module_id'])) {
            $query->where('system_module_id', $filters['system_module_id']);
        }

        if (! empty($filters['lead_assigned_user_id'])) {
            $query->whereHas('lead', fn ($q) => $q->where('assigned_user_id', $filters['lead_assigned_user_id']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['lead_ids'])) {
            $query->whereIn('lead_id', $filters['lead_ids']);
        }

        // Quick views from the counts strip; "overdue" mirrors Requirement::isOverdue().
        match ($filters['view'] ?? null) {
            'open' => $query->where('status', '!=', RequirementStatus::Completed->value),
            'completed' => $query->where('status', RequirementStatus::Completed->value),
            'overdue' => $query->where('status', '!=', RequirementStatus::Completed->value)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', today()),
            default => null,
        };

        return $query;
    }
}
