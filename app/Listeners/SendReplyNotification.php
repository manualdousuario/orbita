<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CommentReplied;
use App\Services\NotificationService;

/**
 * Notifies a comment author when someone replies.
 */
class SendReplyNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(CommentReplied $event): void
    {
        $this->notifications->notifyCommentReply(
            $event->parentComment,
            $event->reply,
            $event->replier,
        );
    }
}
