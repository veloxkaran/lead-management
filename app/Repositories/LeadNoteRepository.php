<?php

namespace App\Repositories;

use App\Models\LeadNote;

class LeadNoteRepository extends BaseRepository
{
    public function __construct(LeadNote $model)
    {
        parent::__construct($model);
    }
}
