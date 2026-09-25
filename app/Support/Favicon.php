<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Favicon
{
    private const ENDPOINT = 'https://icons.duckduckgo.com/ip3/%s.ico';

    public static function url(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $domain = strtolower(trim($domain));

        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain) !== 1) {
            return null;
        }

        $url = sprintf(self::ENDPOINT, $domain);
        $key = 'favicon:'.$domain;
        $cached = Cache::get($key);

        if ($cached === null) {
            [$cached, $ttl] = self::probe($url);
            Cache::put($key, $cached, $ttl);
        }

        return $cached === '1' ? $url : null;
    }

    /**
     * @return array{0: '0'|'1', 1: \DateTimeInterface}
     */
    private static function probe(string $url): array
    {
        try {
            $status = OutboundHttp::send(fn (PendingRequest $http) => $http->timeout(2)->head($url))->status();

            if ($status === 200) {
                return ['1', now()->addDays(30)];
            }

            if ($status === 404) {
                return ['0', now()->addDays(7)];
            }

            Log::warning('Favicon lookup returned unexpected status', ['url' => $url, 'status' => $status]);
        } catch (\Throwable $e) {
            Log::warning('Favicon lookup failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return ['0', now()->addHour()];
    }
}
