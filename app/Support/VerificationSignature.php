<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Validates signed email-verification URLs.
 */
final class VerificationSignature
{
    public const VALID = 'valid';

    public const EXPIRED = 'expired';

    public const INVALID = 'invalid';

    /**
     * Checks the request's signature, returning valid, expired, or invalid.
     *
     * @return self::VALID|self::EXPIRED|self::INVALID
     */
    public static function check(Request $request): string
    {
        if (! self::isCorrect($request)) {
            return self::INVALID;
        }

        return URL::signatureHasNotExpired($request) ? self::VALID : self::EXPIRED;
    }

    private static function isCorrect(Request $request): bool
    {
        $ignore = self::ignoreQuery();

        if (URL::hasCorrectSignature($request, absolute: false, ignoreQuery: $ignore)) {
            return true;
        }

        // TODO: remove 48h after deploy — no config flag needed.
        return URL::hasCorrectSignature($request, absolute: true, ignoreQuery: $ignore);
    }

    private static function ignoreQuery(): Closure
    {
        return static fn (string $parameter): bool => $parameter !== 'expires';
    }
}
