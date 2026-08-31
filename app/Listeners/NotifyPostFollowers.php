<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Events\CommentReplied;
use App\Models\Post;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Notifies everyone following a post when it receives a new comment or reply.
 *
 * Queued because this fans out one email per follower; the other listeners are synchronous.
 */
class NotifyPostFollowers implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(CommentCreated|CommentReplied $event): void
    {
        if ($event instanceof CommentCreated) {
            $this->notifications->notifyPostFollowers($event->post, $event->comment, $event->commenter);

            return;
        }

        $post = Post::query()->find($event->reply->post_id);
        if ($post === null) {
            return;
        }

        $this->notifications->notifyPostFollowers($post, $event->reply, $event->replier, $event->parentComment);
    }
}
