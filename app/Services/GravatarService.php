<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Gravatar;

/**
 * Backfills a user's avatar from Gravatar, server-side.
 */
class GravatarService
{
    public function __construct(private readonly ImageService $images) {}

    /**
     * Fetches and stores the user's Gravatar if they have none.
     */
    public function syncForUser(User $user): bool
    {
        if (! config('orbita.avatar_gravatar_enabled', true)) {
            return false;
        }

        if (filled($user->avatar_url)) {
            return false;
        }

        $path = $this->images->downloadAvatarFromUrl(Gravatar::url((string) $user->email), (int) $user->id);

        if ($path === null) {
            return false;
        }

        $user->forceFill(['avatar_url' => $path])->save();

        return true;
    }
}
