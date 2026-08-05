<?php

namespace App\Repositories;

use App\Enums\RequirementPriority;
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
     * One page entry per company (Lead) — search/status/priority/sprint only
     * decide which companies qualify (any company with at least one matching
     * requirement), since the company-wide status badge is computed from
     * that company's complete requirement set, not a filtered subset.
     */
    public function groupedByCompany(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Lead::query()->whereHas('requirements');

        if (! empty($filters['search'])) {
            $query->where('company_name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['status']) || ! empty($filters['priority']) || ! empty($filters['sprint'])) {
            $query->whereHas('requirements', function (Builder $q) use ($filters) {
                if (! empty($filters['status'])) {
                    $q->where('status', $filters['status']);
                }

                if (! empty($filters['priority'])) {
                    $q->where('priority', $filters['priority']);
                }

                if (! empty($filters['sprint'])) {
                    $q->where('sprint', $filters['sprint']);
                }
            });
        }

        return $query
            ->with(['requirements' => fn ($q) => $this->orderedForDisplay($q)])
            ->orderBy('company_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Every requirement belonging to one company, for that company's
     * dedicated requirement-list page.
     */
    public function forLead(Lead $lead): Collection
    {
        return $this->orderedForDisplay($lead->requirements())->get();
    }

    private function orderedForDisplay($query)
    {
        return $query
            ->with(['assignee', 'creator'])
            ->withCount('comments')
            ->orderByRaw(
                'CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END',
                [
                    RequirementPriority::Urgent->value,
                    RequirementPriority::High->value,
                    RequirementPriority::Medium->value,
                    RequirementPriority::Low->value,
                ]
            )
            ->oldest();
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
        $query = $this->query()->with(['lead', 'assignee', 'creator'])->withCount('comments');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->whereHas('lead', fn ($q) => $q->where('company_name', 'like', $term));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['sprint'])) {
            $query->where('sprint', $filters['sprint']);
        }

        if (! empty($filters['lead_ids'])) {
            $query->whereIn('lead_id', $filters['lead_ids']);
        }

        return $query
            ->orderByRaw(
                'CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END',
                [
                    RequirementPriority::Urgent->value,
                    RequirementPriority::High->value,
                    RequirementPriority::Medium->value,
                    RequirementPriority::Low->value,
                ]
            )
            ->oldest();
    }
}
