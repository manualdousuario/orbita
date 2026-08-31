<?php

namespace App\Support;

use App\Models\Moderation;
use Illuminate\Support\Facades\Auth;

/**
 * Writes a consistent moderation audit-log row for admin actions.
 */
class ModerationLogger
{
    /**
     * Logs an action by a moderator.
     *
     * @param  string  $action  One of: hide, unhide, remove, ban_user, unban_user, pin, unpin,
     *                          restore, lock_comments, unlock_comments, auto_hide,
     *                          referral_stripped, activate_user
     * @param  string  $targetType  One of: post, comment, user, media
     * @param  array<string, mixed>|null  $metadata
     */
    public static function log(
        string $action,
        string $targetType,
        int $targetId,
        ?string $reason = null,
        ?array $metadata = null,
    ): Moderation {
        return Moderation::create([
            'moderator_id' => Auth::id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason ?: 'Ação de moderação pelo administrador',
            'metadata' => $metadata,
        ]);
    }

    public static function system(
        string $action,
        string $targetType,
        int $targetId,
        string $reason,
        ?array $metadata = null,
    ): Moderation {
        return Moderation::create([
            'moderator_id' => null,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
