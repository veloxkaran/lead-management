<?php

namespace App\Repositories;

use App\Models\Meeting;

class MeetingRepository extends BaseRepository
{
    public function __construct(Meeting $model)
    {
        parent::__construct($model);
    }
}
