<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class FeedGeneration
{
    private const KEY = 'ranking:generation';

    private ?int $memo = null;

    public function current(): int
    {
        return $this->memo ??= $this->read();
    }

    public function bump(): void
    {
        $this->memo = null;

        if (Cache::get(self::KEY) === null) {
            $this->seed();

            return;
        }

        if (Cache::increment(self::KEY) === false) {
            $this->seed();
        }
    }

    private function read(): int
    {
        $generation = Cache::get(self::KEY);

        return $generation === null ? $this->seed() : (int) $generation;
    }

    private function seed(): int
    {
        $generation = time();

        Cache::forever(self::KEY, $generation);

        return $generation;
    }
}
