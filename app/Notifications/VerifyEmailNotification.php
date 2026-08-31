<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\RetriesWithBackoff;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued email-verification notification.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable, RetriesWithBackoff;
}
