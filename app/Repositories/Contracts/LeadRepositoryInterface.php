<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface LeadRepositoryInterface extends RepositoryInterface
{
    public function filter(array $filters, int $perPage = 15): LengthAwarePaginator;
}
