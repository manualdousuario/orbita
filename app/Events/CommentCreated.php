<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a comment is created.
 */
class CommentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Post $post,
        public readonly Comment $comment,
        public readonly User $commenter,
    ) {}
}
