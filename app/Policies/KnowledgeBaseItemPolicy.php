<?php

namespace App\Policies;

use App\Models\KnowledgeBaseItem;
use App\Models\User;

class KnowledgeBaseItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnowledgeBaseItem $knowledge_base): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, KnowledgeBaseItem $knowledge_base): bool
    {
        return $knowledge_base->uploaded_by === $user->id || $user->isSuperAdmin();
    }

    public function delete(User $user, KnowledgeBaseItem $knowledge_base): bool
    {
        return $knowledge_base->uploaded_by === $user->id || $user->isSuperAdmin();
    }
}
