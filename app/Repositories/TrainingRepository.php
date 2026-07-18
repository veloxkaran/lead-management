<?php

namespace App\Repositories;

use App\Models\Training;
use Illuminate\Pagination\LengthAwarePaginator;

class TrainingRepository extends BaseRepository
{
    public function __construct(Training $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['lead', 'department', 'conductor']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
