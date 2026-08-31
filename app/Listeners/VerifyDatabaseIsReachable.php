<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fails the health check when the database is unreachable.
 */
final class VerifyDatabaseIsReachable
{
    public function handle(DiagnosingHealth $event): void
    {
        try {
            DB::connection()->select('SELECT 1');
        } catch (\Throwable $e) {
            throw new RuntimeException('Database unreachable: '.$e->getMessage(), previous: $e);
        }
    }
}
