<?php

namespace App\Repositories;

use App\Models\LeadStatus;
use Illuminate\Support\Collection;

class LeadStatusRepository extends BaseRepository
{
    public function __construct(LeadStatus $model)
    {
        parent::__construct($model);
    }

    public function ordered(): Collection
    {
        return $this->query()->ordered()->get();
    }

    public function nextOrder(): int
    {
        return ((int) $this->query()->max('order')) + 1;
    }
}
