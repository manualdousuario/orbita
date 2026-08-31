<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SocialLinkConfirmationRequested;
use App\Services\NotificationService;

/**
 * Sends the social account link confirmation email.
 */
class SendSocialLinkConfirmation
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SocialLinkConfirmationRequested $event): void
    {
        $this->notifications->sendSocialLinkConfirmation(
            $event->user,
            $event->provider,
            $event->token,
        );
    }
}
