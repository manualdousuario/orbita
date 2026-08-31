<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Support\PermanentMailFailure;
use Illuminate\Support\Facades\Log;
use Throwable;

trait RetriesWithBackoff
{
    public int $tries = 5;

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    public function failed(Throwable $exception): void
    {
        $context = ['exception' => $exception->getMessage()];

        if (($failure = PermanentMailFailure::match($exception)) !== null) {
            Log::notice(static::class.' dropped: permanent delivery failure', $context + [
                'reason' => $failure->reason,
                'recipient' => $failure->email,
                'response' => $failure->response,
            ]);

            return;
        }

        Log::error(static::class.' failed permanently after retries', $context);
    }
}
