<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LogRedactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs each HTTP request after the response is sent.
 */
final class LogHttpAccess
{
    private const ATTRIBUTE = 'orbita.access_log';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        Context::add('request_id', $requestId);

        $response = $next($request);

        $request->attributes->set(self::ATTRIBUTE, [
            'request_id' => $requestId,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'route' => $request->route()?->getName(),
        ]);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! config('orbita.logs.access.enabled', true) || $this->isExcluded($request)) {
                return;
            }

            $captured = (array) $request->attributes->get(self::ATTRIBUTE, []);

            $start = defined('LARAVEL_START')
                ? LARAVEL_START
                : (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

            Log::channel('access')->info('http', [
                'request_id' => $captured['request_id'] ?? null,
                'method' => $request->getMethod(),
                'uri' => $this->uri($request),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                'ip' => $request->ip(),
                'user_id' => $captured['user_id'] ?? null,
                'route' => $captured['route'] ?? null,
                'ua' => $this->truncate($request->userAgent(), 256),
                'referer' => $this->truncate($request->headers->get('referer'), 256),
            ]);
        } catch (Throwable) {
            //
        }
    }

    private function isExcluded(Request $request): bool
    {
        $patterns = (array) config('orbita.logs.access.exclude', []);

        return $patterns !== [] && $request->is(...$patterns);
    }

    private function uri(Request $request): string
    {
        $uri = '/'.ltrim($request->path(), '/');
        $query = $request->query();

        if ($query !== []) {
            $uri .= '?'.http_build_query(LogRedactor::queryString($query));
        }

        return (string) $this->truncate($uri, (int) config('orbita.logs.access.max_uri_length', 512));
    }

    private function truncate(?string $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
