<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\UsersMentioned;

/**
 * Resolves @mentions to notifiable users and raises a UsersMentioned event.
 */
class MentionNotifier
{
    public static function dispatch(
        string $content,
        ?string $previousContent,
        string $message,
        string $link,
        int $actorId,
    ): void {
        $mentioned = Markdown::getValidMentions($content);
        if ($mentioned === []) {
            return;
        }

        // Anyone we must NOT notify: the actor, plus (on edit) whoever was already mentioned.
        $exclude = [$actorId => true];
        if ($previousContent !== null) {
            foreach (Markdown::getValidMentions($previousContent) as $prev) {
                $exclude[(int) $prev->id] = true;
            }
        }

        $recipients = [];
        foreach ($mentioned as $user) {
            $id = (int) $user->id;
            if (isset($exclude[$id])) {
                continue;
            }
            $exclude[$id] = true; // also dedupes within the new content
            $recipients[] = $user;
        }

        if ($recipients === []) {
            return;
        }

        event(new UsersMentioned($recipients, $message, $link));
    }
}
