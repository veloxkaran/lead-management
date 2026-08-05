<?php

namespace App\Repositories;

use App\Enums\RawDataStatus;
use App\Models\RawData;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

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

        [$from, $to] = $this->resolveDateRange($filters);

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

    /**
     * Resolves the "Created" date filter into a [from, to] Carbon pair.
     * 'today'/'week'/'month' are computed server-side off the current
     * moment; 'custom' takes whatever date_from/date_to were submitted
     * (either bound may be omitted for an open-ended range). Any other
     * value (or no period at all) applies no date filtering.
     */
    private function resolveDateRange(array $filters): array
    {
        return match ($filters['period'] ?? null) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'custom' => [
                ! empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null,
                ! empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null,
            ],
            default => [null, null],
        };
    }
}
