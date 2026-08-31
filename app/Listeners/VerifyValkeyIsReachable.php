<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

final class VerifyValkeyIsReachable
{
    public function handle(DiagnosingHealth $event): void
    {
        try {
            Redis::connection(config('cache.stores.redis.connection', 'cache'))->ping();
        } catch (\Throwable $e) {
            throw new RuntimeException('Valkey unreachable: '.$e->getMessage(), previous: $e);
        }
    }
}
