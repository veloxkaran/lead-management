<?php

namespace App\Policies;

use App\Models\KnowledgeBaseCategory;
use App\Models\User;

class KnowledgeBaseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, KnowledgeBaseCategory $knowledge_base_category): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, KnowledgeBaseCategory $knowledge_base_category): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, KnowledgeBaseCategory $knowledge_base_category): bool
    {
        return $user->isSuperAdmin();
    }
}
