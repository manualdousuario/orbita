<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Support\PermanentMailFailure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Adds exponential backoff retries to queued mailables.
 */
trait RetriesWithBackoff
{
    public int $tries = 5;

    // 1m, 5m, 15m, 1h, 6h: spaced so hourly/daily rate limits can clear.
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    public function failed(Throwable $exception): void
    {
        $context = [
            'to' => $this->to ?? [],
            'exception' => $exception->getMessage(),
        ];

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
