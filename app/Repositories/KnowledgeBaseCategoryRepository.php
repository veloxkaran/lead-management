<?php

namespace App\Repositories;

use App\Models\KnowledgeBaseCategory;

class KnowledgeBaseCategoryRepository extends BaseRepository
{
    public function __construct(KnowledgeBaseCategory $model)
    {
        parent::__construct($model);
    }
}
