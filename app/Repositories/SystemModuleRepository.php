<?php

namespace App\Repositories;

use App\Models\SystemModule;

class SystemModuleRepository extends BaseRepository
{
    public function __construct(SystemModule $model)
    {
        parent::__construct($model);
    }
}
