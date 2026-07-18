<?php

namespace App\Repositories;

use App\Models\Subscription;
use Illuminate\Pagination\LengthAwarePaginator;

class SubscriptionRepository extends BaseRepository
{
    public function __construct(Subscription $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['lead', 'creator']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
