<?php

namespace App\Repositories;

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
        $query = $this->query()->with(['lead', 'assignee', 'creator'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['lead_ids'])) {
            $query->whereIn('lead_id', $filters['lead_ids']);
        }

        return $query;
    }
}
