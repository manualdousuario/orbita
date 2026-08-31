<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Queue;

final class QueueMetrics
{
    /**
     * Mirrors --queue in docker/s6-rc.d/orbita-queue/run.
     *
     * @var list<string>
     */
    private const QUEUES = ['emails', 'default'];

    public static function pending(): int
    {
        return array_sum(array_map(
            static fn (string $queue): int => Queue::size($queue),
            self::QUEUES,
        ));
    }
}
