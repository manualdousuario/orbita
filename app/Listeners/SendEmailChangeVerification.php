<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EmailChangeRequested;
use App\Services\NotificationService;

/**
 * Sends the verification email to a user's new address.
 */
class SendEmailChangeVerification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(EmailChangeRequested $event): void
    {
        $this->notifications->sendEmailChangeVerification(
            $event->user,
            $event->newEmail,
            $event->token,
        );
    }
}
