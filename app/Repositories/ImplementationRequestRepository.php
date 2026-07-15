<?php

namespace App\Repositories;

use App\Models\ImplementationRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class ImplementationRequestRepository extends BaseRepository
{
    public function __construct(ImplementationRequest $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['lead', 'requester', 'assignee']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['requested_by'])) {
            $query->where('requested_by', $filters['requested_by']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
