<?php

namespace App\Repositories;

use App\Models\ReleaseNote;

class ReleaseNoteRepository extends BaseRepository
{
    public function __construct(ReleaseNote $model)
    {
        parent::__construct($model);
    }
}
