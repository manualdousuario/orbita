<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\GravatarService;
use App\Services\ImageService;
use App\Support\Avatar;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Provisions an avatar for a new user, falling back to a generated one.
 */
class ProvisionAvatarForNewUser implements ShouldQueue
{
    public function __construct(
        private readonly GravatarService $gravatar,
        private readonly ImageService $images,
    ) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        if ($this->gravatar->syncForUser($user)) {
            return;
        }

        $this->storeGeneratedAvatar($user);
    }

    public function storeGeneratedAvatar(User $user): bool
    {
        if (filled($user->avatar_url) || blank($user->username)) {
            return false;
        }

        $path = $this->images->downloadAvatarFromUrl(
            Avatar::pngUrl((string) $user->username),
            (int) $user->id,
        );

        if ($path === null) {
            Log::info('Could not store the generated avatar; the view will keep hotlinking it.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        $user->forceFill(['avatar_url' => $path])->save();

        return true;
    }
}
