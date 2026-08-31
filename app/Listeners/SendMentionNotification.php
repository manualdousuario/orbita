<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UsersMentioned;
use App\Services\NotificationService;

/**
 * Notifies each user mentioned in a post or comment.
 */
class SendMentionNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(UsersMentioned $event): void
    {
        foreach ($event->users as $user) {
            $this->notifications->notifyMention($user, $event->message, $event->link);
        }
    }
}
