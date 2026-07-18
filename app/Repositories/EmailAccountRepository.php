<?php

namespace App\Repositories;

use App\Models\EmailAccount;
use Illuminate\Pagination\LengthAwarePaginator;

class EmailAccountRepository extends BaseRepository
{
    public function __construct(EmailAccount $model)
    {
        parent::__construct($model);
    }

    public function filter(array $filters, int $userId, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->where('user_id', $userId);

        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }
}
