<?php

namespace App\Repositories;

use App\Models\RawData;
use Illuminate\Pagination\LengthAwarePaginator;

class RawDataRepository extends BaseRepository
{
    public function __construct(RawData $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['creator', 'convertedLead'])->withCount('comments');

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('contact_person', 'like', $term)
                ->orWhere('company_name', 'like', $term)
                ->orWhere('phone', 'like', $term));
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
