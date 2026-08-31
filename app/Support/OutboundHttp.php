<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Choke point for outbound calls, with direct-first and proxy-on-failure semantics.
 */
class OutboundHttp
{
    /**
     * Runs the request directly, retrying once through the proxy on connection failure.
     *
     * @param  callable(PendingRequest): Response  $request
     */
    public static function send(callable $request): Response
    {
        try {
            return $request(Http::withOptions([]));
        } catch (ConnectionException $e) {
            $proxy = config('orbita.http_proxy');

            if (empty($proxy)) {
                throw $e;
            }

            Log::info('Outbound HTTP: direct attempt failed, retrying through proxy', [
                'error' => $e->getMessage(),
            ]);

            return $request(Http::withOptions(['proxy' => $proxy]));
        }
    }
}
