<?php

namespace App\Repositories;

use App\Enums\RawDataStatus;
use App\Models\RawData;
use App\Support\PeriodRange;
use Illuminate\Pagination\LengthAwarePaginator;

class RawDataRepository extends BaseRepository
{
    public function __construct(RawData $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['creator', 'convertedLead', 'assignee'])->withCount('comments');

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('contact_person', 'like', $term)
                ->orWhere('company_name', 'like', $term)
                ->orWhere('phone', 'like', $term));
        }

        [$from, $to] = PeriodRange::resolve($filters);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query
            ->orderByRaw(
                'CASE status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END',
                [
                    RawDataStatus::New->value,
                    RawDataStatus::Hold->value,
                    RawDataStatus::NotValid->value,
                    RawDataStatus::ConvertedToLead->value,
                ]
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
