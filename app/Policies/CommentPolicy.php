<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use App\Services\CommentService;

/**
 * Comment authorization: update defers to CommentService; delete/view are owner-or-staff.
 */
class CommentPolicy
{
    public function update(User $user, Comment $comment): bool
    {
        return app(CommentService::class)->canEdit($comment, $user);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->ownerOrStaff($user, $comment);
    }

    public function view(User $user, Comment $comment): bool
    {
        return $this->ownerOrStaff($user, $comment);
    }

    private function ownerOrStaff(User $user, Comment $comment): bool
    {
        return (int) $user->id === (int) $comment->user_id || $user->isStaff();
    }
}
