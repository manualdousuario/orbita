<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\SocialProvider;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event fired when a social account link confirmation is requested.
 */
class SocialLinkConfirmationRequested
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly SocialProvider $provider,
        public readonly string $token,
    ) {}
}
