<?php

namespace App\Repositories;

use App\Models\LeadActivity;
use Illuminate\Pagination\LengthAwarePaginator;

class LeadActivityRepository extends BaseRepository
{
    public function __construct(LeadActivity $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['lead', 'creator']);

        if (! empty($filters['activity_type'])) {
            $query->where('activity_type', $filters['activity_type']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('activity_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('activity_date', '<=', $filters['to']);
        }

        if (! empty($filters['visible_to_user_id'])) {
            $userId = $filters['visible_to_user_id'];
            $query->whereHas('lead', function ($q) use ($userId) {
                $q->where('assigned_user_id', $userId)->orWhere('created_by', $userId);
            });
        }

        return $query->latest('activity_date')->latest('activity_time')->paginate($perPage)->withQueryString();
    }
}
