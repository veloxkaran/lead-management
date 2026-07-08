<?php

namespace App\Repositories;

use App\Models\Requirement;
use Illuminate\Pagination\LengthAwarePaginator;

class RequirementRepository extends BaseRepository
{
    public function __construct(Requirement $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['lead', 'assignee', 'creator']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['lead_ids'])) {
            $query->whereIn('lead_id', $filters['lead_ids']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
