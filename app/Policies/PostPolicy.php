<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Services\PostService;

/**
 * Post authorization: update defers to PostService; delete/viewRevisions are owner-or-staff.
 */
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return app(PostService::class)->canEdit($post, $user);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->ownerOrStaff($user, $post);
    }

    public function viewRevisions(User $user, Post $post): bool
    {
        return $this->ownerOrStaff($user, $post);
    }

    private function ownerOrStaff(User $user, Post $post): bool
    {
        return (int) $user->id === (int) $post->user_id || $user->isStaff();
    }
}
