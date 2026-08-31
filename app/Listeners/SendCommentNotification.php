<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Services\NotificationService;

/**
 * Notifies a post author when a new comment is posted.
 */
class SendCommentNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(CommentCreated $event): void
    {
        $this->notifications->notifyNewCommentOnPost(
            $event->post,
            $event->comment,
            $event->commenter,
        );
    }
}
