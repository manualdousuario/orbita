<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\RetriesWithBackoff;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued reset-password notification.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable, RetriesWithBackoff;
}
