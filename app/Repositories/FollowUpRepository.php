<?php

namespace App\Repositories;

use App\Models\FollowUp;
use Illuminate\Pagination\LengthAwarePaginator;

class FollowUpRepository extends BaseRepository
{
    public function __construct(FollowUp $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['lead', 'creator']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('follow_up_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('follow_up_date', '<=', $filters['to']);
        }

        if (! empty($filters['visible_to_user_id'])) {
            $userId = $filters['visible_to_user_id'];
            $query->whereHas('lead', function ($q) use ($userId) {
                $q->where('assigned_user_id', $userId)->orWhere('created_by', $userId);
            });
        }

        return $query->orderBy('follow_up_date')->orderBy('follow_up_time')->paginate($perPage)->withQueryString();
    }
}
