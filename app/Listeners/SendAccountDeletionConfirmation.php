<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AccountDeletionRequested;
use App\Services\NotificationService;

/**
 * Sends the account deletion confirmation email.
 */
class SendAccountDeletionConfirmation
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(AccountDeletionRequested $event): void
    {
        $this->notifications->sendAccountDeletionConfirmation($event->user, $event->token);
    }
}
