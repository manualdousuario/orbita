<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a comment receives a reply.
 */
class CommentReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Comment $parentComment,
        public readonly Comment $reply,
        public readonly User $replier,
    ) {}
}
