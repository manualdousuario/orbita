<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Redacts configured keys and email addresses from log output.
 */
final class LogRedactor
{
    public const PLACEHOLDER = '[redacted]';

    private const KEYED_VALUE = '/(["\']?\b%s\b["\']?\s*(?:=>|[=:])\s*)(["\']?)([^\s"\',;&})\]]*)\2/i';

    private const EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    public static function queryString(array $query): array
    {
        foreach (self::keys() as $key) {
            if (array_key_exists($key, $query)) {
                $query[$key] = self::PLACEHOLDER;
            }
        }

        return $query;
    }

    public static function text(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        foreach (self::keys() as $key) {
            $pattern = sprintf(self::KEYED_VALUE, preg_quote($key, '/'));

            $value = (string) preg_replace($pattern, '${1}${2}'.self::PLACEHOLDER.'${2}', $value);
        }

        return (string) preg_replace(self::EMAIL, self::PLACEHOLDER, $value);
    }

    /** @return list<string> */
    private static function keys(): array
    {
        $keys = [];

        foreach ((array) config('orbita.logs.access.redact', []) as $key) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
