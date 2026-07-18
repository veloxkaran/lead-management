<?php

namespace App\Policies;

use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    /**
     * Only the comment's author, or whoever manages the parent task
     * (assigner/creator/overseer), can remove it — anyone who can view the
     * task can post one (checked against the Task itself in the controller,
     * not here).
     */
    public function delete(User $user, TaskComment $comment): bool
    {
        return $user->isOverseer()
            || $comment->author_id === $user->id
            || $comment->task->assigned_by === $user->id
            || $comment->task->created_by === $user->id;
    }
}
